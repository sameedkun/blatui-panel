<?php

namespace App\Livewire\Admin\Components;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $currentLocale = 'en';

    /**
     * @var array<string, array{name: string, flag: string, native: string}>
     */
    public array $locales = [
        'en' => [
            'name' => 'English',
            'flag' => '🇬🇧',
            'native' => 'English',
        ],
        'tr' => [
            'name' => 'Turkish',
            'flag' => '🇹🇷',
            'native' => 'Türkçe',
        ],
    ];

    public function mount(): void
    {
        $this->currentLocale = App::getLocale();
    }

    public function switchLocale(string $locale): void
    {
        if (! array_key_exists($locale, $this->locales)) {
            return;
        }

        Cookie::queue('locale', $locale, 60 * 24 * 365);

        $this->redirect(url()->previous(), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.components.language-switcher');
    }
}
