<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_every_error_page_renders_in_the_visitors_language(): void
    {
        Route::middleware('web')->get('/_abort/{code}', fn (int $code) => abort($code));

        $pages = [
            403 => ['uz' => 'Kirish taqiqlangan', 'ru' => 'Доступ запрещён'],
            404 => ['uz' => 'Sahifa topilmadi', 'ru' => 'Страница не найдена'],
            410 => ['uz' => 'Bu havola muddati tugagan', 'ru' => 'Срок действия ссылки истёк'],
            419 => ['uz' => 'Sahifa muddati tugadi', 'ru' => 'Страница устарела'],
            429 => ['uz' => 'Soʻrovlar juda koʻp', 'ru' => 'Слишком много запросов'],
            500 => ['uz' => 'Xatolik yuz berdi', 'ru' => 'Что-то пошло не так'],
            503 => ['uz' => 'Tez orada qaytamiz', 'ru' => 'Скоро вернёмся'],
        ];

        foreach ($pages as $status => $titles) {
            foreach ($titles as $lang => $title) {
                $this->withHeader('Accept-Language', $lang)->get("/_abort/$status")
                    ->assertStatus($status)
                    ->assertSee($title)
                    ->assertSee('<html lang="' . $lang . '"', false)
                    ->assertSee('noindex', false);
            }
        }
    }
}
