<x-layouts.app title="Billing">
    @php
        $symbol = config('inventory.currency_symbol');
    @endphp

    <div x-data="posTerminal({
            scanUrl: '{{ route('billing.scan') }}',
            memberUrl: '{{ route('billing.member') }}',
            enrolUrl: '{{ route('billing.member.store') }}',
            loyalty: {{ Js::from($loyalty) }},
            otpSendUrl: '{{ route('billing.otp.send') }}',
            otpVerifyUrl: '{{ route('billing.otp.verify') }}',
            otpOverrideUrl: '{{ route('billing.otp.override') }}',
            otpRequired: {{ Js::from($whatsappEnabled) }},
        })"
        x-on:keydown.escape="code = ''; dismissSuggestions(); focusScanner()">

        <x-page-header title="Billing" :description="'Next bill '.$nextInvoice.' · scan the label or type the product code'">
            <x-slot:actions>
                <x-button :href="route('sales.index')" variant="secondary" size="sm">Today's bills</x-button>
                <x-button type="button" variant="secondary" size="sm" x-on:click="clearCart()"
                    x-bind:disabled="cart.length === 0">Clear basket</x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('billing.store') }}"
            x-on:submit="if (!canComplete) { $event.preventDefault() }">
            @csrf

            <div class="grid gap-6 xl:grid-cols-3">
                {{-- Basket --}}
                <div class="space-y-4 xl:col-span-2">
                    <div class="card p-4">
                        <label for="scanner" class="form-label">Scan barcode or enter product code</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">▮▯▮</span>
                            <input type="text" id="scanner" x-ref="scanner" x-model="code" autocomplete="off"
                                x-on:input="highlight = -1"
                                x-on:input.debounce.180ms="suggest()"
                                x-on:keydown.enter.prevent="submitCode()"
                                x-on:keydown.arrow-down.prevent="moveHighlight(1)"
                                x-on:keydown.arrow-up.prevent="moveHighlight(-1)"
                                placeholder="Scan the label, or start typing a barcode / SKU / product name"
                                class="form-input py-3 pl-12 text-base">
                            <span x-show="searching" x-cloak
                                class="absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">searching…</span>
                        </div>
                        <p class="form-hint">
                            Matches appear as you type — click one, or use ↑ ↓ and Enter. A scanner sends the code and its own
                            Enter, so scanned items drop straight into the basket.
                        </p>

                        {{-- Live suggestions --}}
                        <div x-show="results.length" x-cloak
                            class="mt-3 max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                            <template x-for="(result, index) in results" :key="result.id">
                                {{-- Highlight tracks the keyboard only: a pointer resting over the
                                     list must never decide what Enter adds. --}}
                                <button type="button" x-on:click="addProduct(result)"
                                    x-bind:class="highlight === index ? 'bg-brand-50 ring-1 ring-brand-300 ring-inset' : ''"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-slate-50">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-800" x-text="result.name"></span>
                                        <span class="block font-mono text-xs text-slate-400">
                                            <span x-text="result.sku"></span>
                                            <template x-if="result.barcode"><span> · <span x-text="result.barcode"></span></span></template>
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-right text-xs">
                                        <span class="block font-semibold text-slate-800">{{ $symbol }}<span x-text="money(result.unit_price)"></span></span>
                                        <span x-bind:class="result.current_stock > 0 ? 'text-slate-400' : 'text-red-500'" class="block">
                                            <span x-text="result.current_stock"></span> <span x-text="result.unit"></span> left
                                        </span>
                                    </span>
                                </button>
                            </template>
                        </div>

                        <p x-show="!searching && !results.length && code.trim().length >= 2" x-cloak
                            class="mt-3 rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-500">
                            No product matches “<span class="font-medium text-slate-700" x-text="code"></span>”.
                        </p>

                        <div x-show="notice" x-cloak class="mt-3 rounded-lg px-3 py-2 text-sm"
                            x-bind:class="notice?.tone === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                            x-text="notice?.message"></div>
                    </div>

                    <x-card>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="table-head w-8">#</th>
                                        <th class="table-head">Item</th>
                                        <th class="table-head w-40 text-center">Qty</th>
                                        <th class="table-head w-28 text-right">Price</th>
                                        <th class="table-head w-24 text-right">Disc %</th>
                                        <th class="table-head w-28 text-right">Amount</th>
                                        <th class="table-head w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(line, index) in cart" :key="line.id">
                                        <tr x-bind:class="overStock(line) ? 'bg-red-50/60' : ''">
                                            <td class="px-3 py-3 text-xs text-slate-400" x-text="index + 1"></td>

                                            <td class="px-3 py-3">
                                                <input type="hidden" x-bind:name="`items[${index}][product_id]`" x-bind:value="line.id">
                                                <span class="block text-sm font-medium text-slate-900" x-text="line.name"></span>
                                                <span class="block font-mono text-xs text-slate-400">
                                                    <span x-text="line.sku"></span> ·
                                                    <span x-text="line.current_stock"></span> <span x-text="line.unit"></span> in stock
                                                </span>
                                                <span x-show="overStock(line)" x-cloak
                                                    class="mt-1 inline-block rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-medium text-red-700">
                                                    More than the shelf holds
                                                </span>
                                            </td>

                                            <td class="px-3 py-3">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button type="button" x-on:click="changeQuantity(index, line.is_weighable ? -0.25 : -1)"
                                                        class="size-7 shrink-0 rounded border border-slate-300 text-slate-600 hover:bg-slate-100">−</button>
                                                    <input type="number" step="0.001" min="0"
                                                        class="form-input w-20 py-1 text-center text-sm"
                                                        x-bind:name="`items[${index}][quantity]`" x-model="line.quantity"
                                                        x-bind:data-cart-qty="index"
                                                        x-on:keydown.enter.prevent="focusScanner()">
                                                    <button type="button" x-on:click="changeQuantity(index, line.is_weighable ? 0.25 : 1)"
                                                        class="size-7 shrink-0 rounded border border-slate-300 text-slate-600 hover:bg-slate-100">+</button>
                                                </div>
                                                <span class="mt-1 block text-center text-[11px] text-slate-400"
                                                    x-text="line.is_weighable ? 'weighed · ' + line.unit : line.unit"></span>
                                            </td>

                                            <td class="px-3 py-3">
                                                <input type="number" step="0.01" min="0" class="form-input py-1 text-right text-sm"
                                                    x-bind:name="`items[${index}][unit_price]`" x-model="line.unit_price">
                                            </td>

                                            <td class="px-3 py-3">
                                                <input type="number" step="0.01" min="0" max="100" placeholder="0"
                                                    class="form-input py-1 text-right text-sm"
                                                    x-bind:name="`items[${index}][discount_percent]`" x-model="line.discount_percent">
                                            </td>

                                            <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">
                                                {{ $symbol }}<span x-text="money(lineNet(line))"></span>
                                            </td>

                                            <td class="px-3 py-3 text-right">
                                                <button type="button" x-on:click="removeLine(index)"
                                                    class="rounded p-1 text-slate-300 hover:bg-red-50 hover:text-red-600">✕</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div x-show="cart.length === 0" x-cloak>
                            <x-empty-state icon="▮▯▮" title="Basket is empty"
                                description="Scan the product label, or type the barcode / SKU above and press Enter." />
                        </div>
                    </x-card>

                    {{-- Quick picks for loose produce that has no label to scan --}}
                    @if ($quickPicks->isNotEmpty())
                        <x-card title="Quick keys" description="Loose produce and fast movers — no barcode needed.">
                            <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($quickPicks as $product)
                                    @php
                                        $payload = [
                                            'id' => $product->id,
                                            'name' => $product->display_name,
                                            'sku' => $product->sku,
                                            'unit' => $product->unit?->code,
                                            'is_weighable' => $product->is_weighable,
                                            'min_sale_quantity' => $product->min_sale_quantity !== null ? (float) $product->min_sale_quantity : null,
                                            'current_stock' => (float) $product->current_stock,
                                            'unit_price' => (float) $product->selling_price,
                                            'vat_rate' => (float) $product->tax_rate,
                                            'price_includes_tax' => $product->price_includes_tax,
                                        ];
                                    @endphp

                                    <button type="button" x-on:click="addProduct({{ Js::from($payload) }})"
                                        class="rounded-lg border border-slate-200 bg-white p-2.5 text-left transition hover:border-brand-400 hover:bg-brand-50">
                                        <span class="block truncate text-xs font-medium text-slate-800">{{ $product->display_name }}</span>
                                        <span class="mt-0.5 block text-[11px] text-slate-500">
                                            {{ $symbol }}{{ number_format((float) $product->selling_price, 2) }} / {{ $product->unit?->code }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </x-card>
                    @endif
                </div>

                {{-- Payment --}}
                <div class="space-y-4">
                    <div class="card sticky top-20 overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-3.5">
                            <h2 class="text-sm font-semibold text-slate-900">Payment</h2>
                        </div>

                        <div class="space-y-4 p-5">
                            <input type="hidden" name="customer_id" x-bind:value="member ? member.id : ''">
                            <input type="hidden" name="redeem_points" x-bind:value="appliedRedeemPoints || ''">
                            <input type="hidden" name="redemption_otp_id" x-bind:value="otpId || ''">

                            {{-- Loyalty member: scan the card here or into the product box, or type the mobile number. --}}
                            <div class="rounded-lg border border-slate-200 p-3">
                                <template x-if="!member">
                                    <div>
                                        <label for="member_search" class="form-label">Loyalty member</label>
                                        <input type="text" id="member_search" x-model="memberQuery" autocomplete="off"
                                            x-on:input.debounce.250ms="searchMember(false)"
                                            x-on:keydown.enter.prevent="searchMember(true)"
                                            placeholder="Scan card, or mobile number / name" class="form-input">

                                        <p x-show="memberError" x-cloak class="mt-2 text-xs text-red-600" x-text="memberError"></p>

                                        <div x-show="memberResults.length" x-cloak
                                            class="mt-2 max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                                            <template x-for="result in memberResults" :key="result.id">
                                                <button type="button" x-on:click="attachMember(result)"
                                                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-slate-50">
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-medium text-slate-800" x-text="result.name"></span>
                                                        <span class="block text-xs text-slate-400" x-text="result.phone"></span>
                                                    </span>
                                                    <span class="shrink-0 text-right text-xs text-slate-500">
                                                        <span class="block" x-text="result.tier"></span>
                                                        <span class="block"><span x-text="result.points_balance"></span> pts</span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>

                                        <button type="button" x-show="!showEnrol" x-on:click="startEnrol()"
                                            class="mt-2 text-xs font-medium text-brand-700 hover:underline">+ Sign up a new member</button>

                                        <div x-show="showEnrol" x-cloak class="mt-2 space-y-2 rounded-lg bg-slate-50 p-3">
                                            <input type="text" x-model="enrolName" x-on:keydown.enter.prevent placeholder="Name" class="form-input">
                                            <input type="tel" x-model="enrolPhone" x-on:keydown.enter.prevent placeholder="Mobile number" class="form-input">
                                            <label class="flex items-center gap-2 text-xs text-slate-600">
                                                <input type="checkbox" x-model="enrolOptIn" class="rounded border-slate-300 text-brand-600">
                                                Happy to receive offers
                                            </label>
                                            <p x-show="enrolError" x-cloak class="text-xs text-red-600" x-text="enrolError"></p>
                                            <div class="flex gap-2">
                                                <x-button type="button" size="sm" x-on:click="enrolMember()" x-bind:disabled="enrolling">Sign up &amp; attach</x-button>
                                                <x-button type="button" size="sm" variant="ghost" x-on:click="showEnrol = false">Cancel</x-button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="member">
                                    <div>
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <a x-bind:href="member.url" target="_blank"
                                                    class="block truncate text-sm font-semibold text-slate-900 hover:text-brand-700" x-text="member.name"></a>
                                                <p class="text-xs text-slate-500">
                                                    <span x-text="member.tier ?? 'Member'"></span> ·
                                                    <span x-text="member.card ?? member.phone"></span>
                                                </p>
                                            </div>
                                            <button type="button" x-on:click="detachMember()"
                                                class="shrink-0 text-xs text-slate-400 hover:text-red-600">Remove</button>
                                        </div>

                                        <div class="mt-2 flex justify-between text-xs">
                                            <span class="text-slate-500">Points balance</span>
                                            <span class="font-semibold text-slate-800">
                                                <span x-text="member.points_balance.toLocaleString()"></span> pts ·
                                                {{ $symbol }}<span x-text="money(member.points_value)"></span>
                                            </span>
                                        </div>

                                        <div x-show="loyalty.enabled && member.points_balance >= loyalty.min_redeem_points" class="mt-2">
                                            <label for="redeem_points_input" class="form-label">Redeem points</label>
                                            <div class="flex gap-2">
                                                <input type="number" id="redeem_points_input" min="0" step="1" x-model="redeemPoints"
                                                    x-on:keydown.enter.prevent placeholder="0" class="form-input text-right">
                                                <button type="button" x-on:click="useMaxPoints()"
                                                    class="shrink-0 rounded-lg border border-slate-300 px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                                    Max <span x-text="redeemLimit.toLocaleString()"></span>
                                                </button>
                                            </div>
                                            <p class="form-hint">
                                                <span x-text="loyalty.min_redeem_points"></span> minimum · up to
                                                <span x-text="loyalty.max_redeem_percent"></span>% of the bill
                                            </p>

                                            {{-- The member confirms their own points being spent. Only
                                                 shown where WhatsApp is set up: with no way to send a
                                                 code, asking for one would just close the till. --}}
                                            @if ($whatsappEnabled)
                                                <div x-show="redeemPoints > 0" x-cloak class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                                    <template x-if="!otpId">
                                                        <div>
                                                            <button type="button" x-on:click="sendRedemptionCode()"
                                                                x-bind:disabled="otpSending"
                                                                class="w-full rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50">
                                                                <span x-show="!otpSending">Send code to member's WhatsApp</span>
                                                                <span x-show="otpSending" x-cloak>Sending…</span>
                                                            </button>
                                                            <p class="form-hint">Their points cannot be spent without it.</p>
                                                        </div>
                                                    </template>

                                                    <template x-if="otpSent && !otpVerified">
                                                        <div>
                                                            <label for="otp_code" class="form-label">Code from the member</label>
                                                            <div class="flex gap-2">
                                                                <input type="text" id="otp_code" inputmode="numeric" maxlength="6"
                                                                    x-model="otpCode" x-on:keydown.enter.prevent="verifyRedemptionCode()"
                                                                    placeholder="6 digits" class="form-input text-center font-mono tracking-widest">
                                                                <button type="button" x-on:click="verifyRedemptionCode()"
                                                                    class="shrink-0 rounded-lg border border-slate-300 px-3 text-xs font-medium text-slate-700 hover:bg-white">
                                                                    Confirm
                                                                </button>
                                                            </div>
                                                            <p class="form-hint">Sent to <span x-text="otpSentTo"></span></p>
                                                        </div>
                                                    </template>

                                                    <p x-show="otpVerified" x-cloak class="text-xs font-medium text-emerald-700">
                                                        ✓ Confirmed — the points can be used on this bill.
                                                    </p>
                                                    <p x-show="otpError" x-cloak class="mt-1.5 text-xs text-red-600" x-text="otpError"></p>

                                                    @can('manage-subscription')
                                                        <button type="button" x-show="otpError && !otpVerified" x-cloak
                                                            x-on:click="overrideRedemptionCode()"
                                                            class="mt-1.5 text-xs font-medium text-slate-500 underline hover:text-slate-800">
                                                            Owner: approve without a code
                                                        </button>
                                                    @endcan
                                                </div>
                                            @endif
                                        </div>

                                        <p x-show="loyalty.enabled" class="mt-2 rounded bg-brand-50 px-2 py-1 text-[11px] text-brand-800">
                                            Earns <strong x-text="pointsToEarn.toLocaleString()"></strong> points on this bill
                                        </p>
                                    </div>
                                </template>
                            </div>

                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Items</dt>
                                    <dd class="font-medium text-slate-800">
                                        <span x-text="cart.length"></span> line(s) · <span x-text="totalUnits"></span> units
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Gross</dt>
                                    <dd class="font-medium text-slate-800">{{ $symbol }}<span x-text="money(itemsGross)"></span></dd>
                                </div>
                                <div class="flex justify-between" x-show="lineDiscountTotal > 0" x-cloak>
                                    <dt class="text-slate-500">Line discounts</dt>
                                    <dd class="font-medium text-slate-800">− {{ $symbol }}<span x-text="money(lineDiscountTotal)"></span></dd>
                                </div>
                            </dl>

                            <div>
                                <label for="bill_discount" class="form-label">Bill discount ({{ trim($symbol) }})</label>
                                <input type="number" step="0.01" min="0" id="bill_discount" name="bill_discount"
                                    x-model="billDiscount" placeholder="0.00" class="form-input text-right">
                            </div>

                            <dl class="space-y-2 border-t border-slate-200 pt-3 text-sm">
                                <div class="flex justify-between" x-show="loyaltyDiscount > 0" x-cloak>
                                    <dt class="text-slate-500">Points redeemed (<span x-text="appliedRedeemPoints.toLocaleString()"></span>)</dt>
                                    <dd class="font-medium text-brand-700">− {{ $symbol }}<span x-text="money(loyaltyDiscount)"></span></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Subtotal (excl. VAT)</dt>
                                    <dd class="font-medium text-slate-800">{{ $symbol }}<span x-text="money(breakdown.subtotal)"></span></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">VAT</dt>
                                    <dd class="font-medium text-slate-800">{{ $symbol }}<span x-text="money(breakdown.vat)"></span></dd>
                                </div>
                                <div class="flex items-baseline justify-between border-t border-slate-200 pt-2.5">
                                    <dt class="font-semibold text-slate-900">Total</dt>
                                    <dd class="text-2xl font-semibold text-brand-700">{{ $symbol }}<span x-text="money(grandTotal)"></span></dd>
                                </div>
                            </dl>

                            <div>
                                <span class="form-label">Payment method</span>
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach ($paymentMethods as $method)
                                        <label class="cursor-pointer">
                                            <input type="radio" class="peer sr-only" name="payment_method"
                                                value="{{ $method->value }}" x-model="paymentMethod"
                                                x-on:change="amountPaid = ''">
                                            <span class="block rounded-lg border border-slate-300 px-2 py-2 text-center text-xs font-medium text-slate-600
                                                peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">
                                                {{ $method->shortLabel() }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div x-show="needsTendering" x-cloak class="space-y-2">
                                <label for="amount_paid" class="form-label">Cash received</label>
                                <input type="number" step="0.01" min="0" id="amount_paid" name="amount_paid"
                                    x-model="amountPaid" placeholder="0.00" class="form-input text-right text-lg">
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" x-on:click="tender('exact')"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Exact</button>
                                    @foreach ([50, 100, 200, 500] as $note)
                                        <button type="button" x-on:click="tender({{ $note }})"
                                            class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">{{ $note }}</button>
                                    @endforeach
                                </div>
                                <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                    <span class="text-slate-500">Change due</span>
                                    <span class="font-semibold text-slate-900">{{ $symbol }}<span x-text="money(changeDue)"></span></span>
                                </div>
                            </div>

                            {{-- Credit closes the bill with money still owed, chased on the member's account. --}}
                            <div x-show="isCredit" x-cloak class="space-y-2">
                                <label for="credit_part_payment" class="form-label">Paying now (optional)</label>
                                <input type="number" step="0.01" min="0" id="credit_part_payment" name="amount_paid"
                                    x-model="amountPaid" placeholder="0.00" class="form-input text-right text-lg">
                                <div class="flex justify-between rounded-lg bg-amber-50 px-3 py-2 text-sm">
                                    <span class="text-amber-700">Goes on account</span>
                                    <span class="font-semibold text-amber-900">{{ $symbol }}<span x-text="money(creditOutstanding)"></span></span>
                                </div>
                                <p class="text-xs text-slate-500" x-show="member" x-cloak>
                                    Owed by <span class="font-medium" x-text="member?.name"></span> until paid. Track it under Reports → Credit Due.
                                </p>
                            </div>

                            <button type="button" x-on:click="showCustomer = !showCustomer"
                                class="text-xs font-medium text-brand-700 hover:underline">
                                <span x-text="showCustomer ? '− Hide customer details' : '+ Add customer details'"></span>
                            </button>

                            <div x-show="showCustomer" x-cloak class="space-y-2">
                                <input type="text" name="customer_name" x-model="customerName" placeholder="Customer name" class="form-input">
                                <input type="text" name="customer_phone" x-model="customerPhone" placeholder="Mobile number" class="form-input">
                                <input type="text" name="customer_vat_number" x-model="customerVat" placeholder="Customer VAT number" class="form-input">
                            </div>

                            <p x-show="blockers" x-cloak class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="blockers"></p>

                            <x-button type="submit" size="lg" class="w-full" x-bind:disabled="!canComplete">
                                Complete sale &amp; print bill
                            </x-button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-layouts.app>
