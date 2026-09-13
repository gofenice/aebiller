<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\BarcodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BarcodeLabelTest extends TestCase
{
    use RefreshDatabase;

    protected BarcodeGenerator $barcodes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barcodes = app(BarcodeGenerator::class);
    }

    /**
     * 5901234123457 is the worked example in the EAN-13 specification.
     */
    public function test_it_works_out_the_check_digit_the_standard_way(): void
    {
        $this->assertSame(7, $this->barcodes->checkDigit('590123412345'));
        $this->assertSame(4, $this->barcodes->checkDigit('628100702123'));
    }

    public function test_it_rejects_anything_that_is_not_a_well_formed_ean_13(): void
    {
        $this->assertTrue($this->barcodes->isValidEan13('5901234123457'));

        $this->assertFalse($this->barcodes->isValidEan13('5901234123456'), 'wrong check digit');
        $this->assertFalse($this->barcodes->isValidEan13('590123412345'), 'too short');
        $this->assertFalse($this->barcodes->isValidEan13('59012341234571'), 'too long');
        $this->assertFalse($this->barcodes->isValidEan13('590123412345X'), 'not all digits');
        $this->assertFalse($this->barcodes->isValidEan13(null));
    }

    public function test_a_barcode_that_would_not_scan_is_never_drawn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->barcodes->ean13('5901234123456');
    }

    /**
     * The bar pattern is fixed by the standard: 95 modules, guard bars at both
     * ends and in the middle, and a left half whose parities encode the first
     * digit. Getting any of that wrong produces a picture no scanner reads.
     */
    public function test_it_draws_the_module_pattern_the_specification_requires(): void
    {
        $svg = $this->barcodes->ean13('5901234123457');

        // width="1" keeps this to the bars, skipping the white background rect.
        preg_match_all('/<rect x="(\d+)" y="0" width="1"/', $svg, $matches);
        $bars = array_map('intval', $matches[1]);

        // 11 modules of quiet zone each side, 95 modules of code.
        $this->assertSame(11, min($bars), 'the left guard must start after the quiet zone');
        $this->assertSame(105, max($bars), 'the right guard must end the 95-module code');

        // Guard bars run longer than the digit bars. There are exactly six of
        // them: two at each end and two in the middle.
        preg_match_all('/<rect x="(\d+)" y="0" width="1" height="(\d+)"/', $svg, $detail);
        $heights = array_combine(array_map('intval', $detail[1]), array_map('intval', $detail[2]));

        $tallest = max($heights);

        $this->assertGreaterThan(min($heights), $tallest, 'guard bars must run longer than data bars');
        $this->assertSame([11, 13, 57, 59, 103, 105], array_keys($heights, $tallest));
    }

    public function test_the_printed_digits_are_the_barcode_itself(): void
    {
        $svg = $this->barcodes->ean13('6281007021234');

        $this->assertStringContainsString('>6<', $svg, 'the first digit sits in the quiet zone');
        $this->assertStringContainsString('>281007<', $svg);
        $this->assertStringContainsString('>021234<', $svg);
    }

    public function test_the_label_sheet_prints_only_products_a_scanner_could_read(): void
    {
        $good = Product::factory()->create(['name' => 'Scannable Item', 'barcode' => '5901234123457']);
        $bad = Product::factory()->create(['name' => 'Broken Barcode Item', 'barcode' => '5901234123456']);
        $none = Product::factory()->create(['name' => 'Unlabelled Item', 'barcode' => null]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('products.labels'))
            ->assertOk();

        $response->assertSee($good->name);
        $response->assertDontSee($bad->name);
        $response->assertDontSee($none->name);
        $response->assertSee('1 product(s) were left off', false);
    }

    public function test_inactive_products_are_left_off_unless_asked_for(): void
    {
        Product::factory()->create(['name' => 'Retired Item', 'barcode' => '5901234123457', 'is_active' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('products.labels'))->assertDontSee('Retired Item');
        $this->actingAs($user)->get(route('products.labels', ['status' => 'all']))->assertSee('Retired Item');
    }
}
