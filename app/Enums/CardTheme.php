<?php

namespace App\Enums;

/**
 * The printed look of a loyalty card. Each tier picks one, so a Gold member
 * carries a gold card.
 */
enum CardTheme: string
{
    case Emerald = 'emerald';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::Emerald => 'Emerald (store green)',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
            self::Platinum => 'Platinum (black)',
        };
    }

    /**
     * Colours for the card face and the band on its back. `logo` is the ink
     * of the store mark, which sits on a foreground-coloured tile.
     *
     * @return array{background: string, foreground: string, muted: string, accent: string, band: string, logo: string}
     */
    public function palette(): array
    {
        return match ($this) {
            self::Emerald => [
                'background' => 'linear-gradient(135deg, #064e3b 0%, #047857 48%, #10b981 100%)',
                'foreground' => '#ffffff',
                'muted' => 'rgba(236, 253, 245, 0.78)',
                'accent' => '#fde68a',
                'band' => '#047857',
                'logo' => '#047857',
            ],
            self::Silver => [
                'background' => 'linear-gradient(135deg, #94a3b8 0%, #e2e8f0 38%, #f8fafc 55%, #cbd5e1 100%)',
                'foreground' => '#0f172a',
                'muted' => 'rgba(15, 23, 42, 0.62)',
                'accent' => '#047857',
                'band' => '#475569',
                'logo' => '#f8fafc',
            ],
            self::Gold => [
                'background' => 'linear-gradient(135deg, #78350f 0%, #b45309 34%, #f59e0b 68%, #fcd34d 100%)',
                'foreground' => '#fffbeb',
                'muted' => 'rgba(255, 251, 235, 0.8)',
                'accent' => '#ffffff',
                'band' => '#b45309',
                'logo' => '#92400e',
            ],
            self::Platinum => [
                'background' => 'linear-gradient(135deg, #020617 0%, #1e293b 58%, #334155 100%)',
                'foreground' => '#f8fafc',
                'muted' => 'rgba(226, 232, 240, 0.7)',
                'accent' => '#6ee7b7',
                'band' => '#0f172a',
                'logo' => '#0f172a',
            ],
        };
    }

    /**
     * The badge colour used for the tier on screen.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Emerald => 'green',
            self::Silver => 'slate',
            self::Gold => 'amber',
            self::Platinum => 'violet',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $theme): array => [$theme->value => $theme->label()])
            ->all();
    }
}
