# Telegram Tour Bot Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Telegram bot that parses qui-quo.com tour links, generates beautiful selling posts via Claude API, and publishes them to a Telegram channel on schedule.

**Architecture:** Laravel 11 webhook-based Telegram bot. Incoming links are parsed with symfony/dom-crawler, tour data is sent to Claude API for post generation, manager approves via inline buttons, delayed Laravel jobs publish to the channel.

**Tech Stack:** Laravel 11, PHP 8.3, nutgram/nutgram, symfony/dom-crawler, Claude API (claude-sonnet-4-6), MySQL, Redis queues.

**Spec:** `docs/superpowers/specs/2026-03-13-telegram-tour-bot-design.md`

---

## Chunk 1: Project Scaffolding & Database

### Task 1: Create Laravel Project

**Files:**
- Create: entire Laravel 11 project scaffold

- [ ] **Step 1: Create Laravel project**

```bash
cd /c/projects/pegasus
composer create-project laravel/laravel . "11.*"
```

- [ ] **Step 2: Install dependencies**

```bash
composer require nutgram/nutgram
composer require symfony/dom-crawler
composer require symfony/css-selector
```

- [ ] **Step 3: Configure .env**

Add to `.env`:
```
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHANNEL_ID=
TELEGRAM_ADMIN_ID=
TELEGRAM_WEBHOOK_SECRET=
CLAUDE_API_KEY=
CLAUDE_MODEL=claude-sonnet-4-6

QUEUE_CONNECTION=redis
APP_TIMEZONE=UTC
```

Add same keys to `.env.example` (without values).

- [ ] **Step 4: Add config file for bot**

Create `config/telegram.php`:
```php
<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    'admin_id' => (int) env('TELEGRAM_ADMIN_ID'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
];
```

Create `config/claude.php`:
```php
<?php

return [
    'api_key' => env('CLAUDE_API_KEY'),
    'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
    'max_regenerations' => 3,
    'system_prompt' => <<<'PROMPT'
Ты — копирайтер турагентства. Генерируй продающий пост для Telegram-канала.

Правила:
- Используй эмодзи для структуры
- Заголовок с огоньком и флагом страны
- Отель и звёзды
- Детали перелёта (город вылета, даты, время, авиакомпания)
- Количество ночей, тип номера, питание
- Удобства отеля (бассейн, Wi-Fi и т.д.)
- Цена общая и на человека
- Призыв к действию: "Пишите в личку — мест мало, тур горит!"
- Контакт: @PegasTouristik_MegaAlmaty
- Максимум 3500 символов (лимит Telegram)
- НЕ используй HTML-разметку, пиши чистый текст с эмодзи
- Не выдумывай данные — используй только то, что передано
PROMPT,
];
```

- [ ] **Step 5: Commit**

```bash
git init
git add -A
git commit -m "chore: scaffold Laravel 11 project with dependencies"
```

---

### Task 2: Database Migrations

**Files:**
- Create: `database/migrations/xxxx_create_users_table.php` (modify existing)
- Create: `database/migrations/xxxx_create_tour_batches_table.php`
- Create: `database/migrations/xxxx_create_tours_table.php`
- Create: `database/migrations/xxxx_create_posts_table.php`

- [ ] **Step 1: Modify existing users migration**

Edit `database/migrations/0001_01_01_000000_create_users_table.php`. Replace the `up()` method's Schema::create('users') block:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->bigInteger('telegram_id')->unique();
    $table->string('name');
    $table->boolean('is_admin')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

Remove the `password_reset_tokens` and `sessions` tables from this migration (not needed).

- [ ] **Step 2: Create tour_batches migration**

```bash
php artisan make:migration create_tour_batches_table
```

```php
Schema::create('tour_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('source_url');
    $table->timestamps();
});
```

- [ ] **Step 3: Create tours migration**

```bash
php artisan make:migration create_tours_table
```

```php
Schema::create('tours', function (Blueprint $table) {
    $table->id();
    $table->foreignId('batch_id')->constrained('tour_batches')->cascadeOnDelete();
    $table->string('hotel_name');
    $table->unsignedTinyInteger('stars');
    $table->string('country');
    $table->string('location');
    $table->string('departure_city');
    $table->string('airline');
    $table->dateTime('flight_out');
    $table->dateTime('flight_back');
    $table->string('nights');
    $table->string('room_type');
    $table->string('meal_plan');
    $table->string('guests');
    $table->unsignedBigInteger('price');
    $table->json('amenities')->nullable();
    $table->json('raw_data')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Create posts migration**

```bash
php artisan make:migration create_posts_table
```

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('generated_text');
    $table->enum('status', ['draft', 'approved', 'scheduled', 'published', 'rejected'])->default('draft');
    $table->unsignedTinyInteger('regeneration_count')->default(0);
    $table->dateTime('publish_at')->nullable();
    $table->dateTime('published_at')->nullable();
    $table->unsignedBigInteger('telegram_message_id')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 5: Run migrations to verify**

```bash
php artisan migrate
```

Expected: All migrations run successfully.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat: add database migrations for users, tour_batches, tours, posts"
```

---

### Task 3: Eloquent Models

**Files:**
- Modify: `app/Models/User.php`
- Create: `app/Models/TourBatch.php`
- Create: `app/Models/Tour.php`
- Create: `app/Models/Post.php`
- Test: `tests/Feature/Models/ModelRelationshipTest.php`

- [ ] **Step 1: Write failing test for model relationships**

Create `tests/Feature/Models/ModelRelationshipTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_tour_batches(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Test User',
            'is_admin' => true,
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/SG30-UT47',
        ]);

        $this->assertTrue($user->tourBatches->contains($batch));
    }

    public function test_tour_batch_has_many_tours(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Test User',
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/SG30-UT47',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 5,
            'country' => 'Вьетнам',
            'location' => 'Фукуок',
            'departure_city' => 'Алматы',
            'airline' => 'SCAT Airlines',
            'flight_out' => '2026-03-14 22:20:00',
            'flight_back' => '2026-03-20 09:30:00',
            'nights' => '5+1',
            'room_type' => 'Superior Bungalow',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 740000,
        ]);

        $this->assertTrue($batch->tours->contains($tour));
    }

    public function test_tour_has_one_post(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Test User',
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/SG30-UT47',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 5,
            'country' => 'Вьетнам',
            'location' => 'Фукуок',
            'departure_city' => 'Алматы',
            'airline' => 'SCAT Airlines',
            'flight_out' => '2026-03-14 22:20:00',
            'flight_back' => '2026-03-20 09:30:00',
            'nights' => '5+1',
            'room_type' => 'Superior Bungalow',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 740000,
        ]);

        $post = Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Test post text',
            'status' => 'draft',
        ]);

        $this->assertTrue($tour->post->is($post));
        $this->assertTrue($post->tour->is($tour));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Models/ModelRelationshipTest.php
```

Expected: FAIL (models don't exist yet or lack relationships).

- [ ] **Step 3: Implement User model**

Edit `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'telegram_id',
        'name',
        'is_admin',
        'is_active',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tourBatches(): HasMany
    {
        return $this->hasMany(TourBatch::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

- [ ] **Step 4: Implement TourBatch model**

Create `app/Models/TourBatch.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourBatch extends Model
{
    protected $fillable = [
        'user_id',
        'source_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'batch_id');
    }
}
```

- [ ] **Step 5: Implement Tour model**

Create `app/Models/Tour.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tour extends Model
{
    protected $fillable = [
        'batch_id',
        'hotel_name',
        'stars',
        'country',
        'location',
        'departure_city',
        'airline',
        'flight_out',
        'flight_back',
        'nights',
        'room_type',
        'meal_plan',
        'guests',
        'price',
        'amenities',
        'raw_data',
    ];

    protected $casts = [
        'stars' => 'integer',
        'price' => 'integer',
        'flight_out' => 'datetime',
        'flight_back' => 'datetime',
        'amenities' => 'array',
        'raw_data' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TourBatch::class, 'batch_id');
    }

    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }
}
```

- [ ] **Step 6: Implement Post model**

Create `app/Models/Post.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'tour_id',
        'user_id',
        'generated_text',
        'status',
        'regeneration_count',
        'publish_at',
        'published_at',
        'telegram_message_id',
    ];

    protected $casts = [
        'regeneration_count' => 'integer',
        'publish_at' => 'datetime',
        'published_at' => 'datetime',
        'telegram_message_id' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canRegenerate(): bool
    {
        return $this->regeneration_count < config('claude.max_regenerations');
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Models/ModelRelationshipTest.php
```

Expected: All 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/ tests/Feature/Models/
git commit -m "feat: add Eloquent models with relationships"
```

---

## Chunk 2: QuiQuoParser Service

### Task 4: QuiQuoParser

**Files:**
- Create: `app/Services/QuiQuoParser.php`
- Test: `tests/Feature/Services/QuiQuoParserTest.php`
- Create: `tests/fixtures/quiquo-sample.html`

- [ ] **Step 1: Save a fixture HTML file**

Open `https://qui-quo.com/SG30-UT47` in a browser, save the full HTML source to `tests/fixtures/quiquo-sample.html`. This will be used for testing without hitting the live site.

Alternatively, use curl:
```bash
curl -s -o tests/fixtures/quiquo-sample.html "https://qui-quo.com/SG30-UT47"
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/Services/QuiQuoParserTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Services\QuiQuoParser;
use Tests\TestCase;

class QuiQuoParserTest extends TestCase
{
    public function test_parses_tours_from_html(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/quiquo-sample.html'));
        $parser = new QuiQuoParser();
        $tours = $parser->parseHtml($html);

        $this->assertIsArray($tours);
        $this->assertGreaterThan(0, count($tours));

        $first = $tours[0];
        $this->assertArrayHasKey('hotel_name', $first);
        $this->assertArrayHasKey('stars', $first);
        $this->assertArrayHasKey('country', $first);
        $this->assertArrayHasKey('location', $first);
        $this->assertArrayHasKey('price', $first);
        $this->assertArrayHasKey('nights', $first);
        $this->assertArrayHasKey('meal_plan', $first);
        $this->assertArrayHasKey('airline', $first);
        $this->assertArrayHasKey('departure_city', $first);
        $this->assertArrayHasKey('room_type', $first);
        $this->assertArrayHasKey('guests', $first);
        $this->assertArrayHasKey('flight_out', $first);
        $this->assertArrayHasKey('flight_back', $first);
    }

    public function test_returns_empty_array_for_empty_html(): void
    {
        $parser = new QuiQuoParser();
        $tours = $parser->parseHtml('<html><body></body></html>');

        $this->assertIsArray($tours);
        $this->assertCount(0, $tours);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test tests/Feature/Services/QuiQuoParserTest.php
```

Expected: FAIL — class QuiQuoParser does not exist.

- [ ] **Step 4: Implement QuiQuoParser**

Create `app/Services/QuiQuoParser.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class QuiQuoParser
{
    /**
     * Fetch and parse tours from a qui-quo.com URL.
     *
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException
     */
    public function fetchAndParse(string $url): array
    {
        $response = Http::timeout(15)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch URL: {$url} (status: {$response->status()})");
        }

        return $this->parseHtml($response->body());
    }

    /**
     * Parse HTML and extract tour data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseHtml(string $html): array
    {
        $crawler = new Crawler($html);
        $tours = [];

        // qui-quo.com renders tour cards — the exact selectors need to be
        // determined from the fixture HTML. This is a best-effort parse;
        // the actual implementation will be refined after inspecting the
        // fixture DOM structure.
        $crawler->filter('.tour-card, .hotel-card, [data-tour]')->each(function (Crawler $node) use (&$tours) {
            try {
                $tour = $this->extractTourData($node);
                if ($tour !== null) {
                    $tours[] = $tour;
                }
            } catch (\Throwable) {
                // Skip malformed tour cards
            }
        });

        return $tours;
    }

    private function extractTourData(Crawler $node): ?array
    {
        // Extract data from DOM node. Selectors will be refined
        // once we inspect the actual qui-quo.com HTML structure.
        $hotelName = $this->text($node, '.hotel-name, .tour-hotel-name, h3, h4');
        if (empty($hotelName)) {
            return null;
        }

        return [
            'hotel_name' => $hotelName,
            'stars' => $this->extractStars($node),
            'country' => $this->text($node, '.country, .tour-country'),
            'location' => $this->text($node, '.location, .tour-location, .resort'),
            'departure_city' => $this->text($node, '.departure, .city-from'),
            'airline' => $this->text($node, '.airline, .tour-airline'),
            'flight_out' => $this->text($node, '.flight-out, .departure-date'),
            'flight_back' => $this->text($node, '.flight-back, .return-date'),
            'nights' => $this->text($node, '.nights, .tour-nights'),
            'room_type' => $this->text($node, '.room, .room-type'),
            'meal_plan' => $this->text($node, '.meal, .meal-plan'),
            'guests' => $this->text($node, '.guests, .pax'),
            'price' => $this->extractPrice($node),
            'amenities' => $this->extractAmenities($node),
        ];
    }

    private function text(Crawler $node, string $selector): string
    {
        try {
            return trim($node->filter($selector)->first()->text(''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractStars(Crawler $node): int
    {
        $text = $this->text($node, '.stars, .hotel-stars, .rating');
        preg_match('/(\d)/', $text, $m);
        return (int) ($m[1] ?? 0);
    }

    private function extractPrice(Crawler $node): int
    {
        $text = $this->text($node, '.price, .tour-price, .total-price');
        $cleaned = preg_replace('/[^\d]/', '', $text);
        return (int) $cleaned;
    }

    private function extractAmenities(Crawler $node): array
    {
        $amenities = [];
        $node->filter('.amenity, .facility, .hotel-feature')->each(function (Crawler $el) use (&$amenities) {
            $text = trim($el->text(''));
            if ($text !== '') {
                $amenities[] = $text;
            }
        });
        return $amenities;
    }
}
```

**IMPORTANT NOTE — Human-Assisted Step:**

The CSS selectors above are initial guesses. qui-quo.com may render content via JavaScript (SPA), in which case `Http::get` returns an empty shell. This task requires human involvement:

1. **Check if the page is server-rendered or SPA:**
   - Open the fixture HTML (`tests/fixtures/quiquo-sample.html`) and search for tour data (hotel names, prices). If the data is present in the HTML, DOM parsing works.
   - If the HTML is an empty shell with JS bundles, we need to switch approach: look for JSON data embedded in `<script>` tags (e.g., `window.__INITIAL_STATE__` or similar), or intercept the API calls the page makes via browser DevTools Network tab and call those APIs directly.

2. **Map CSS selectors from the actual DOM:**
   - Open the qui-quo URL in a browser, right-click a tour card → Inspect
   - Note the actual CSS classes/selectors for: tour card container, hotel name, stars, country, location, price, dates, airline, nights, meal plan, room type, guests, amenities
   - Update `QuiQuoParser.php` selectors accordingly

3. **If the site uses an internal API:**
   - Check the Network tab for XHR/fetch calls that load tour data as JSON
   - If found, replace DOM parsing with a direct API call — this is more reliable
   - Update `QuiQuoParser::fetchAndParse()` to call the API endpoint and parse JSON

- [ ] **Step 5: Inspect fixture and determine parsing strategy**

Open the fixture HTML and determine:
- Is tour data present in the HTML? → use DOM parsing, fix selectors
- Is data loaded via JS from an API? → find the API URL, switch to JSON parsing

- [ ] **Step 6: Update QuiQuoParser with correct selectors or API approach**

Based on step 5 findings, update the parser implementation. This may require rewriting `parseHtml()` entirely.

- [ ] **Step 7: Run tests and iterate until PASS**

```bash
php artisan test tests/Feature/Services/QuiQuoParserTest.php
```

If tests fail, adjust selectors/parsing logic. Re-run until PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/QuiQuoParser.php tests/Feature/Services/ tests/fixtures/
git commit -m "feat: add QuiQuoParser service for parsing tour data"
```

---

## Chunk 3: Claude Service

### Task 5: ClaudeService

**Files:**
- Create: `app/Services/ClaudeService.php`
- Test: `tests/Feature/Services/ClaudeServiceTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Services/ClaudeServiceTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Services\ClaudeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeServiceTest extends TestCase
{
    public function test_generates_post_from_tour_data(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Generated tour post text here'],
                ],
            ], 200),
        ]);

        $service = new ClaudeService();
        $tourData = [
            'hotel_name' => 'Hien Minh Bungalow',
            'stars' => 3,
            'country' => 'Вьетнам',
            'location' => 'Фукуок',
            'departure_city' => 'Алматы',
            'airline' => 'SCAT Airlines',
            'flight_out' => '2026-03-14 22:20',
            'flight_back' => '2026-03-20 09:30',
            'nights' => '5+1',
            'room_type' => 'Superior Bungalow',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 740000,
            'amenities' => ['бассейн', 'Wi-Fi', 'парковка'],
        ];

        $result = $service->generatePost($tourData);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com/v1/messages')
                && $request->header('x-api-key')[0] !== ''
                && $request['model'] === config('claude.model');
        });
    }

    public function test_throws_on_api_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate_limited'], 429),
        ]);

        $service = new ClaudeService();

        $this->expectException(\RuntimeException::class);
        $service->generatePost(['hotel_name' => 'Test']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Services/ClaudeServiceTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement ClaudeService**

Create `app/Services/ClaudeService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ClaudeService
{
    public function generatePost(array $tourData): string
    {
        $userMessage = $this->buildUserMessage($tourData);

        $response = Http::withHeaders([
            'x-api-key' => config('claude.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('claude.model'),
            'max_tokens' => 2048,
            'system' => config('claude.system_prompt'),
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Claude API error: {$response->status()} — " . $response->body()
            );
        }

        $text = $response->json('content.0.text', '');

        if (empty($text)) {
            throw new \RuntimeException('Claude API returned empty response');
        }

        return $text;
    }

    private function buildUserMessage(array $tour): string
    {
        $amenitiesList = '';
        if (!empty($tour['amenities'])) {
            $amenitiesList = implode(', ', $tour['amenities']);
        }

        $pricePerPerson = '';
        if ($tour['price'] > 0) {
            $pp = number_format(intdiv($tour['price'], 2), 0, '', ' ');
            $total = number_format($tour['price'], 0, '', ' ');
            $pricePerPerson = "Цена: {$total} тенге на двоих (~{$pp} тенге с человека)";
        }

        return <<<MSG
        Сгенерируй продающий пост для этого тура:

        Отель: {$tour['hotel_name']}
        Звёзды: {$tour['stars']}
        Страна: {$tour['country']}
        Курорт: {$tour['location']}
        Город вылета: {$tour['departure_city']}
        Авиакомпания: {$tour['airline']}
        Вылет туда: {$tour['flight_out']}
        Вылет обратно: {$tour['flight_back']}
        Ночей: {$tour['nights']}
        Номер: {$tour['room_type']}
        Питание: {$tour['meal_plan']}
        Размещение: {$tour['guests']}
        {$pricePerPerson}
        Удобства: {$amenitiesList}
        MSG;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/Services/ClaudeServiceTest.php
```

Expected: 2 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ClaudeService.php tests/Feature/Services/ClaudeServiceTest.php
git commit -m "feat: add ClaudeService for generating tour posts via Claude API"
```

---

## Chunk 4: Telegram Bot Setup & Handlers

### Task 6: Nutgram Bot Configuration & Webhook

**Files:**
- Modify: `routes/api.php`
- Create: `app/Telegram/Middleware/AuthorizeUser.php`

- [ ] **Step 1: Publish nutgram config**

```bash
php artisan vendor:publish --provider="SergiX44\Nutgram\NutgramServiceProvider"
```

- [ ] **Step 2: Configure nutgram**

Edit `config/nutgram.php` — set token source:

```php
'token' => env('TELEGRAM_BOT_TOKEN'),
```

- [ ] **Step 3: Create auth middleware**

Create `app/Telegram/Middleware/AuthorizeUser.php`:

```php
<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class AuthorizeUser
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $telegramId = $bot->userId();

        $user = User::where('telegram_id', $telegramId)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            // Auto-create admin on first use
            if ($telegramId === config('telegram.admin_id')) {
                $user = User::create([
                    'telegram_id' => $telegramId,
                    'name' => $bot->user()->first_name ?? 'Admin',
                    'is_admin' => true,
                ]);
            } else {
                $bot->sendMessage('У вас нет доступа к этому боту.');
                return;
            }
        }

        // Store user in bot data for handlers
        $bot->set('db_user', $user);
        $next($bot);
    }
}
```

- [ ] **Step 4: Register webhook route**

Edit `routes/api.php`:

```php
use SergiX44\Nutgram\Nutgram;

Route::post('/telegram/webhook', function (Nutgram $bot) {
    $bot->run();
});
```

- [ ] **Step 5: Commit**

```bash
git add app/Telegram/ routes/api.php config/nutgram.php
git commit -m "feat: configure nutgram bot with auth middleware and webhook route"
```

---

### Task 7: LinkHandler — Process Incoming Tour Links

**Files:**
- Create: `app/Telegram/Handlers/LinkHandler.php`
- Create: `app/Jobs/ProcessTourBatchJob.php`
- Modify: `app/Providers/AppServiceProvider.php` (register bot handlers)

- [ ] **Step 1: Create ProcessTourBatchJob**

The heavy work (fetching URL, parsing, calling Claude API for each tour) is dispatched to a queue job to avoid webhook timeout (Telegram has 60s limit, Claude calls can take 30s+ each).

Create `app/Jobs/ProcessTourBatchJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Services\ClaudeService;
use App\Services\QuiQuoParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ProcessTourBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes max

    public function __construct(
        public TourBatch $batch,
        public int $chatId,
    ) {}

    public function handle(QuiQuoParser $parser, ClaudeService $claude, Nutgram $bot): void
    {
        try {
            $toursData = $parser->fetchAndParse($this->batch->source_url);
        } catch (\Throwable) {
            $bot->sendMessage(
                text: 'Не удалось загрузить страницу, попробуйте позже.',
                chat_id: $this->chatId,
            );
            return;
        }

        if (empty($toursData)) {
            $bot->sendMessage(
                text: 'Туры не найдены по этой ссылке.',
                chat_id: $this->chatId,
            );
            return;
        }

        $bot->sendMessage(
            text: "Найдено " . count($toursData) . " туров. Генерирую посты...",
            chat_id: $this->chatId,
        );

        foreach ($toursData as $tourData) {
            $tour = Tour::create(array_merge($tourData, [
                'batch_id' => $this->batch->id,
                'raw_data' => $tourData,
            ]));

            try {
                $generatedText = $claude->generatePost($tourData);
            } catch (\Throwable) {
                $bot->sendMessage(
                    text: "Не удалось сгенерировать пост для {$tour->hotel_name}.",
                    chat_id: $this->chatId,
                );
                continue;
            }

            $post = Post::create([
                'tour_id' => $tour->id,
                'user_id' => $this->batch->user_id,
                'generated_text' => $generatedText,
                'status' => 'draft',
            ]);

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ Одобрить', callback_data: "approve:{$post->id}"),
                    InlineKeyboardButton::make('❌ Отклонить', callback_data: "reject:{$post->id}"),
                )
                ->addRow(
                    InlineKeyboardButton::make('🔄 Перегенерировать', callback_data: "regenerate:{$post->id}"),
                );

            $bot->sendMessage(
                text: $generatedText,
                chat_id: $this->chatId,
                reply_markup: $keyboard,
            );
        }

        $bot->sendMessage(
            text: 'Все туры обработаны!',
            chat_id: $this->chatId,
        );
    }
}
```

- [ ] **Step 2: Create LinkHandler**

Create `app/Telegram/Handlers/LinkHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Jobs\ProcessTourBatchJob;
use App\Models\TourBatch;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class LinkHandler
{
    public function __invoke(Nutgram $bot, string $url): void
    {
        /** @var User $user */
        $user = $bot->get('db_user');

        // Validate URL
        if (!preg_match('#https?://qui-quo\.com/.+#', $url)) {
            $bot->sendMessage('Отправьте ссылку с qui-quo.com');
            return;
        }

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => $url,
        ]);

        $bot->sendMessage('Парсю подборку, подождите...');

        // Dispatch to queue to avoid webhook timeout
        ProcessTourBatchJob::dispatch($batch, $bot->chatId());
    }
}
```

- [ ] **Step 2: Register handlers in bot setup**

Create `app/Telegram/BotServiceProvider.php`:

```php
<?php

namespace App\Telegram;

use App\Telegram\Handlers\ApproveHandler;
use App\Telegram\Handlers\LinkHandler;
use App\Telegram\Handlers\RegenerateHandler;
use App\Telegram\Handlers\RejectHandler;
use App\Telegram\Handlers\ScheduleHandler;
use App\Telegram\Middleware\AuthorizeUser;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\ServiceProvider;

class BotServiceProvider extends ServiceProvider
{
    public function boot(Nutgram $bot): void
    {
        $bot->middleware(AuthorizeUser::class);

        // Handle qui-quo links
        $bot->onText('.*qui-quo\.com\/.+', LinkHandler::class);

        // Callback queries
        $bot->onCallbackQueryData('approve:{id}', ApproveHandler::class);
        $bot->onCallbackQueryData('reject:{id}', RejectHandler::class);
        $bot->onCallbackQueryData('regenerate:{id}', RegenerateHandler::class);
        $bot->onCallbackQueryData('schedule:{id}:{option}', ScheduleHandler::class);
        $bot->onCallbackQueryData('schedule_custom:{id}', [ScheduleHandler::class, 'promptCustom']);

        // Admin commands
        $bot->onCommand('add {telegram_id} {name}', \App\Telegram\Handlers\AdminHandler::class);
        $bot->onCommand('remove {telegram_id}', [\App\Telegram\Handlers\AdminHandler::class, 'remove']);
        $bot->onCommand('start', function (Nutgram $bot) {
            $bot->sendMessage('Привет! Отправь мне ссылку с qui-quo.com и я сгенерирую посты для канала.');
        });
    }
}
```

Register in `config/app.php` providers or `bootstrap/providers.php`:

```php
\App\Telegram\BotServiceProvider::class,
```

- [ ] **Step 3: Commit**

```bash
git add app/Telegram/
git commit -m "feat: add LinkHandler and bot registration"
```

---

### Task 8: ApproveHandler & ScheduleHandler

**Files:**
- Create: `app/Telegram/Handlers/ApproveHandler.php`
- Create: `app/Telegram/Handlers/ScheduleHandler.php`

- [ ] **Step 1: Create ApproveHandler**

Create `app/Telegram/Handlers/ApproveHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ApproveHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $post = DB::transaction(function () use ($id) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post || $post->status !== 'draft') {
                return null;
            }

            $post->update(['status' => 'approved']);
            return $post;
        });

        if (!$post) {
            $bot->answerCallbackQuery(text: 'Пост уже обработан.');
            return;
        }

        $bot->answerCallbackQuery(text: 'Одобрено! Выберите время публикации.');

        $now = now()->timezone('Asia/Almaty');

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    'Через 1 час',
                    callback_data: "schedule:{$post->id}:1h"
                ),
                InlineKeyboardButton::make(
                    'Через 3 часа',
                    callback_data: "schedule:{$post->id}:3h"
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    "Сегодня 18:00",
                    callback_data: "schedule:{$post->id}:today_18"
                ),
                InlineKeyboardButton::make(
                    "Завтра 10:00",
                    callback_data: "schedule:{$post->id}:tomorrow_10"
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    '✏️ Ввести вручную',
                    callback_data: "schedule_custom:{$post->id}"
                ),
            );

        $bot->editMessageReplyMarkup(
            message_id: $bot->callbackQuery()->message->message_id,
            reply_markup: $keyboard,
        );
    }
}
```

- [ ] **Step 2: Create ScheduleHandler**

Create `app/Telegram/Handlers/ScheduleHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;

class ScheduleHandler
{
    public function __invoke(Nutgram $bot, string $id, string $option): void
    {
        $post = DB::transaction(function () use ($id) {
            $post = Post::lockForUpdate()->find($id);
            if (!$post || $post->status !== 'approved') {
                return null;
            }
            return $post;
        });

        if (!$post) {
            $bot->answerCallbackQuery(text: 'Пост уже запланирован или обработан.');
            return;
        }

        $almatyNow = now()->timezone('Asia/Almaty');

        $publishAt = match ($option) {
            '1h' => $almatyNow->copy()->addHour(),
            '3h' => $almatyNow->copy()->addHours(3),
            'today_18' => $almatyNow->copy()->setTime(18, 0),
            'tomorrow_10' => $almatyNow->copy()->addDay()->setTime(10, 0),
            default => null,
        };

        if (!$publishAt) {
            $bot->answerCallbackQuery(text: 'Неизвестная опция.');
            return;
        }

        // If chosen time is in the past, push to tomorrow
        if ($publishAt->isPast()) {
            $publishAt->addDay();
        }

        $publishAtUtc = $publishAt->utc();

        $post->update([
            'status' => 'scheduled',
            'publish_at' => $publishAtUtc,
        ]);

        PublishPostJob::dispatch($post)->delay($publishAtUtc);

        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup(
            message_id: $bot->callbackQuery()->message->message_id,
        );
        $bot->sendMessage(
            "📅 Пост запланирован на {$publishAt->format('d.m.Y H:i')} (Алматы)"
        );
    }

    public function promptCustom(Nutgram $bot, string $id): void
    {
        $bot->answerCallbackQuery();
        $bot->sendMessage(
            "Введите дату и время публикации в формате:\n"
            . "`{$id} ДД.ММ ЧЧ:ММ`\n\n"
            . "Например: `{$id} 15.03 18:00`",
            parse_mode: 'Markdown',
        );
    }
}
```

- [ ] **Step 3: Add custom time text handler to BotServiceProvider**

Add to `BotServiceProvider::boot()`:

```php
// Handle manual time input: "post_id DD.MM HH:MM"
$bot->onText('{id} {date} {time}', function (Nutgram $bot, string $id, string $date, string $time) {
    // Only process if it matches the schedule format
    if (!preg_match('/^\d+$/', $id) || !preg_match('/^\d{2}\.\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        return; // Not a schedule command, ignore
    }

    $post = Post::find($id);
    if (!$post || $post->status !== 'approved') {
        $bot->sendMessage('Пост не найден или уже обработан.');
        return;
    }

    try {
        $year = now()->year;
        $publishAt = Carbon::createFromFormat(
            'd.m.Y H:i',
            "{$date}.{$year} {$time}",
            'Asia/Almaty'
        );
    } catch (\Throwable) {
        $bot->sendMessage('Неверная дата. Формат: ID ДД.ММ ЧЧ:ММ');
        return;
    }

    $publishAtUtc = $publishAt->utc();
    $post->update([
        'status' => 'scheduled',
        'publish_at' => $publishAtUtc,
    ]);

    PublishPostJob::dispatch($post)->delay($publishAtUtc);
    $bot->sendMessage("📅 Пост запланирован на {$publishAt->format('d.m.Y H:i')} (Алматы)");
});
```

Add `use Carbon\Carbon;` and `use App\Models\Post;` to the file imports.

- [ ] **Step 4: Commit**

```bash
git add app/Telegram/Handlers/ApproveHandler.php app/Telegram/Handlers/ScheduleHandler.php app/Telegram/BotServiceProvider.php
git commit -m "feat: add ApproveHandler and ScheduleHandler with time selection"
```

---

### Task 9: RejectHandler & RegenerateHandler

**Files:**
- Create: `app/Telegram/Handlers/RejectHandler.php`
- Create: `app/Telegram/Handlers/RegenerateHandler.php`

- [ ] **Step 1: Create RejectHandler**

Create `app/Telegram/Handlers/RejectHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;

class RejectHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $post = DB::transaction(function () use ($id) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post || !in_array($post->status, ['draft', 'approved'])) {
                return null;
            }

            $post->update(['status' => 'rejected']);
            return $post;
        });

        if (!$post) {
            $bot->answerCallbackQuery(text: 'Пост уже обработан.');
            return;
        }

        $bot->answerCallbackQuery(text: 'Пост отклонён.');

        $bot->deleteMessage(
            message_id: $bot->callbackQuery()->message->message_id,
        );
    }
}
```

- [ ] **Step 2: Create RegenerateHandler**

Create `app/Telegram/Handlers/RegenerateHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use App\Services\ClaudeService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RegenerateHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $post = Post::with('tour')->find($id);

        if (!$post || $post->status !== 'draft') {
            $bot->answerCallbackQuery(text: 'Пост уже обработан.');
            return;
        }

        if (!$post->canRegenerate()) {
            $bot->answerCallbackQuery(
                text: 'Лимит перегенераций исчерпан (макс ' . config('claude.max_regenerations') . ').',
                show_alert: true,
            );
            return;
        }

        $bot->answerCallbackQuery(text: 'Генерирую новый вариант...');

        $claude = app(ClaudeService::class);

        try {
            $newText = $claude->generatePost($post->tour->raw_data ?? $post->tour->toArray());
        } catch (\Throwable) {
            $bot->sendMessage('Не удалось перегенерировать пост, попробуйте позже.');
            return;
        }

        $post->update([
            'generated_text' => $newText,
            'regeneration_count' => $post->regeneration_count + 1,
        ]);

        $remaining = config('claude.max_regenerations') - $post->regeneration_count;

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Одобрить', callback_data: "approve:{$post->id}"),
                InlineKeyboardButton::make('❌ Отклонить', callback_data: "reject:{$post->id}"),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    "🔄 Перегенерировать ({$remaining} осталось)",
                    callback_data: "regenerate:{$post->id}"
                ),
            );

        $bot->editMessageText(
            text: $newText,
            message_id: $bot->callbackQuery()->message->message_id,
            reply_markup: $keyboard,
        );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Telegram/Handlers/RejectHandler.php app/Telegram/Handlers/RegenerateHandler.php
git commit -m "feat: add RejectHandler and RegenerateHandler"
```

---

### Task 10: AdminHandler

**Files:**
- Create: `app/Telegram/Handlers/AdminHandler.php`

- [ ] **Step 1: Create AdminHandler**

Create `app/Telegram/Handlers/AdminHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class AdminHandler
{
    public function __invoke(Nutgram $bot, string $telegram_id, string $name): void
    {
        /** @var User $admin */
        $admin = $bot->get('db_user');

        if (!$admin->is_admin) {
            $bot->sendMessage('Только администратор может добавлять менеджеров.');
            return;
        }

        $user = User::updateOrCreate(
            ['telegram_id' => (int) $telegram_id],
            ['name' => $name, 'is_active' => true],
        );

        $bot->sendMessage("Менеджер {$name} (ID: {$telegram_id}) добавлен.");
    }

    public function remove(Nutgram $bot, string $telegram_id): void
    {
        /** @var User $admin */
        $admin = $bot->get('db_user');

        if (!$admin->is_admin) {
            $bot->sendMessage('Только администратор может удалять менеджеров.');
            return;
        }

        $user = User::where('telegram_id', (int) $telegram_id)->first();

        if (!$user) {
            $bot->sendMessage('Менеджер не найден.');
            return;
        }

        $user->update(['is_active' => false]);
        $bot->sendMessage("Менеджер {$user->name} деактивирован.");
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Telegram/Handlers/AdminHandler.php
git commit -m "feat: add AdminHandler for managing bot users"
```

---

## Chunk 5: Publishing & Scheduler

### Task 11: PublishPostJob

**Files:**
- Create: `app/Jobs/PublishPostJob.php`
- Create: `app/Services/TelegramPublisher.php`
- Test: `tests/Feature/Jobs/PublishPostJobTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Jobs/PublishPostJobTest.php`:

```php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Models\User;
use App\Services\TelegramPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishPostJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_scheduled_post(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Admin',
            'is_admin' => true,
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/test',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 5,
            'country' => 'Test',
            'location' => 'Test',
            'departure_city' => 'Алматы',
            'airline' => 'Test Air',
            'flight_out' => now(),
            'flight_back' => now()->addDays(7),
            'nights' => '7',
            'room_type' => 'Standard',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 500000,
        ]);

        $post = Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Test post for publishing',
            'status' => 'scheduled',
            'publish_at' => now()->subMinute(),
        ]);

        $publisher = $this->mock(TelegramPublisher::class);
        $publisher->shouldReceive('publishToChannel')
            ->once()
            ->with($post->generated_text)
            ->andReturn(12345);
        $publisher->shouldReceive('notifyUser')
            ->once();

        $job = new PublishPostJob($post);
        $job->handle($publisher);

        $post->refresh();
        $this->assertEquals('published', $post->status);
        $this->assertEquals(12345, $post->telegram_message_id);
        $this->assertNotNull($post->published_at);
    }

    public function test_skips_non_scheduled_post(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Admin',
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/test',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test',
            'stars' => 3,
            'country' => 'Test',
            'location' => 'Test',
            'departure_city' => 'Алматы',
            'airline' => 'Test',
            'flight_out' => now(),
            'flight_back' => now()->addDays(5),
            'nights' => '5',
            'room_type' => 'Standard',
            'meal_plan' => 'BB',
            'guests' => '2',
            'price' => 300000,
        ]);

        $post = Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Test',
            'status' => 'published', // already published
        ]);

        $publisher = $this->mock(TelegramPublisher::class);
        $publisher->shouldNotReceive('publishToChannel');

        $job = new PublishPostJob($post);
        $job->handle($publisher);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Jobs/PublishPostJobTest.php
```

Expected: FAIL — classes not found.

- [ ] **Step 3: Create TelegramPublisher service**

Create `app/Services/TelegramPublisher.php`:

```php
<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;

class TelegramPublisher
{
    public function __construct(private Nutgram $bot) {}

    /**
     * Publish text to the configured Telegram channel.
     *
     * @return int Telegram message ID
     */
    public function publishToChannel(string $text): int
    {
        if (mb_strlen($text) > 4096) {
            throw new \RuntimeException('Post text exceeds Telegram 4096 character limit');
        }

        $message = $this->bot->sendMessage(
            text: $text,
            chat_id: config('telegram.channel_id'),
            parse_mode: 'HTML',
        );

        return $message->message_id;
    }

    /**
     * Send notification to a user.
     */
    public function notifyUser(int $telegramId, string $text): void
    {
        $this->bot->sendMessage(
            text: $text,
            chat_id: $telegramId,
        );
    }
}
```

- [ ] **Step 4: Create PublishPostJob**

Create `app/Jobs/PublishPostJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\TelegramPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Post $post) {}

    public function handle(TelegramPublisher $publisher): void
    {
        $this->post->refresh();

        // Idempotency: only publish if still scheduled
        if ($this->post->status !== 'scheduled') {
            return;
        }

        $messageId = $publisher->publishToChannel($this->post->generated_text);

        $this->post->update([
            'status' => 'published',
            'published_at' => now(),
            'telegram_message_id' => $messageId,
        ]);

        $publisher->notifyUser(
            $this->post->user->telegram_id,
            "✅ Пост опубликован в канал: {$this->post->tour->hotel_name}"
        );
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Jobs/PublishPostJobTest.php
```

Expected: 2 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/PublishPostJob.php app/Services/TelegramPublisher.php tests/Feature/Jobs/
git commit -m "feat: add PublishPostJob and TelegramPublisher service"
```

---

### Task 12: CheckStuckPostsCommand

**Files:**
- Create: `app/Console/Commands/CheckStuckPostsCommand.php`
- Modify: `routes/console.php` (register scheduler)
- Test: `tests/Feature/Console/CheckStuckPostsCommandTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Console/CheckStuckPostsCommandTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckStuckPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_jobs_for_stuck_posts(): void
    {
        Queue::fake();

        $user = User::create(['telegram_id' => 123, 'name' => 'Test']);
        $batch = TourBatch::create(['user_id' => $user->id, 'source_url' => 'https://qui-quo.com/test']);
        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Stuck Hotel',
            'stars' => 4,
            'country' => 'Test',
            'location' => 'Test',
            'departure_city' => 'Алматы',
            'airline' => 'Test',
            'flight_out' => now(),
            'flight_back' => now()->addDays(7),
            'nights' => '7',
            'room_type' => 'Standard',
            'meal_plan' => 'AI',
            'guests' => '2',
            'price' => 1000000,
        ]);

        // Stuck post: scheduled but publish_at is in the past
        Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Stuck post',
            'status' => 'scheduled',
            'publish_at' => now()->subHour(),
        ]);

        $this->artisan('posts:check-stuck')->assertSuccessful();

        Queue::assertPushed(PublishPostJob::class, 1);
    }

    public function test_ignores_non_stuck_posts(): void
    {
        Queue::fake();

        $user = User::create(['telegram_id' => 123, 'name' => 'Test']);
        $batch = TourBatch::create(['user_id' => $user->id, 'source_url' => 'https://qui-quo.com/test']);
        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Future Hotel',
            'stars' => 5,
            'country' => 'Test',
            'location' => 'Test',
            'departure_city' => 'Алматы',
            'airline' => 'Test',
            'flight_out' => now(),
            'flight_back' => now()->addDays(7),
            'nights' => '7',
            'room_type' => 'Deluxe',
            'meal_plan' => 'BB',
            'guests' => '2',
            'price' => 2000000,
        ]);

        // Future post: scheduled but publish_at is in the future
        Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Future post',
            'status' => 'scheduled',
            'publish_at' => now()->addHour(),
        ]);

        $this->artisan('posts:check-stuck')->assertSuccessful();

        Queue::assertNotPushed(PublishPostJob::class);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Console/CheckStuckPostsCommandTest.php
```

Expected: FAIL.

- [ ] **Step 3: Implement command**

Create `app/Console/Commands/CheckStuckPostsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use Illuminate\Console\Command;

class CheckStuckPostsCommand extends Command
{
    protected $signature = 'posts:check-stuck';
    protected $description = 'Find and re-dispatch stuck scheduled posts';

    public function handle(): int
    {
        $stuckPosts = Post::where('status', 'scheduled')
            ->where('publish_at', '<', now())
            ->get();

        foreach ($stuckPosts as $post) {
            PublishPostJob::dispatch($post);
            $this->info("Re-dispatched post #{$post->id}: {$post->tour->hotel_name}");
        }

        $this->info("Found {$stuckPosts->count()} stuck post(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register in scheduler**

Edit `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('posts:check-stuck')->everyFiveMinutes();
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Console/CheckStuckPostsCommandTest.php
```

Expected: 2 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/CheckStuckPostsCommand.php routes/console.php tests/Feature/Console/
git commit -m "feat: add CheckStuckPostsCommand with 5-minute scheduler"
```

---

## Chunk 6: Final Integration & Cleanup

### Task 13: Remove Unused Laravel Defaults

**Files:**
- Modify: various Laravel default files

- [ ] **Step 1: Clean up unused defaults**

- Remove `database/migrations/0001_01_01_000001_create_cache_table.php` (use Redis)
- Keep `database/migrations/0001_01_01_000002_create_jobs_table.php` — it contains the `failed_jobs` table needed for tracking failed queue jobs
- Remove default User factory if not using it
- Remove `resources/views/welcome.blade.php` (no web UI)
- Remove web routes from `routes/web.php` (keep empty)

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "chore: remove unused Laravel defaults"
```

---

### Task 14: Run Full Test Suite

- [ ] **Step 1: Run all tests**

```bash
php artisan test
```

Expected: All tests PASS.

- [ ] **Step 2: Fix any failures and re-run**

If any test fails, debug and fix. Re-run until all pass.

- [ ] **Step 3: Final commit if needed**

```bash
git add -A
git commit -m "fix: resolve test failures"
```

---

### Task 15: Deployment Prep

**Files:**
- Modify: `.env.example`

- [ ] **Step 1: Verify .env.example has all required keys**

Ensure `.env.example` contains:
```
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHANNEL_ID=
TELEGRAM_ADMIN_ID=
TELEGRAM_WEBHOOK_SECRET=
CLAUDE_API_KEY=
CLAUDE_MODEL=claude-sonnet-4-6
QUEUE_CONNECTION=redis
```

- [ ] **Step 2: Test artisan commands work**

```bash
php artisan route:list
php artisan schedule:list
```

Verify webhook route and scheduler are registered.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: finalize deployment configuration"
```

---

## Post-Implementation: Deployment Steps

After all tasks are complete, deploy to VPS:

1. Push to git remote
2. Clone/pull on VPS
3. `composer install --no-dev`
4. Configure `.env` with real values
5. `php artisan migrate`
6. Set up supervisor for `php artisan queue:work redis`
7. Add cron: `* * * * * php artisan schedule:run`
8. Set webhook: `php artisan nutgram:hook:set https://yourdomain.com/api/telegram/webhook`
9. Test by sending a qui-quo.com link to the bot
