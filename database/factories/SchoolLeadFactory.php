<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolLead>
 */
class SchoolLeadFactory extends Factory
{
    protected $model = SchoolLead::class;

    /** @var list<string> */
    private const FIRST_NAMES = [
        'Вася',
        'Петя',
        'Игорь',
        'Дима',
        'Саша',
        'Кирилл',
        'Никита',
        'Артём',
        'Миша',
        'Женя',
        'Анастасия',
        'Маша',
        'Катя',
        'Оля',
        'Лена',
        'Настя',
        'Даша',
        'Света',
        'Алина',
        'Юля',
    ];

    /** @var list<string> */
    private const COMMENTS = [
        'Интересует пробное занятие для сына, 8 лет.',
        'Хотим записаться на футбол, удобно по вечерам.',
        'Перезвоните, пожалуйста, после 18:00.',
        'Ребёнку 6 лет, есть ли группа для начинающих?',
        'Уточните стоимость абонемента на месяц.',
        'Пришли с сайта, хотим посмотреть расписание.',
        'Нужна секция рядом с метро, подскажите филиал.',
        'Сын занимался раньше, ищем группу посильнее.',
        'Можно ли прийти на одно занятие без абонемента?',
        'Интересует летний лагерь или смена.',
        'Дочь 10 лет, опыт есть, нужна тренировка 2 раза в неделю.',
        'Хотим начать с пробного, потом оформить абонемент.',
        'Есть ли места в группе на субботу утром?',
        'Напишите в WhatsApp, звонить неудобно.',
        'Спрашивают про форму и инвентарь для первого занятия.',
    ];

    public function definition(): array
    {
        $utmSources = ['google', 'yandex', 'vk', 'telegram', 'direct', 'referral'];
        $utmMediums = ['cpc', 'organic', 'social', 'email', 'banner'];
        $utmCampaigns = ['spring', 'summer', 'trial', 'brand', 'retarget'];

        return [
            'partner_id'            => Partner::factory(),
            'partner_widget_id'     => null,
            'district_id'           => null,
            'location_id'           => null,
            'name'                  => $this->faker->randomElement(self::FIRST_NAMES),
            'phone'                 => '+7 9' . $this->faker->numerify('## ###-##-##'),
            'school_lead_status_id' => fn () => SchoolLeadStatus::systemNewId(),
            'comment'               => $this->faker->optional(0.35)->randomElement(self::COMMENTS),
            'utm_source'            => $this->faker->randomElement($utmSources),
            'utm_medium'            => $this->faker->randomElement($utmMediums),
            'utm_campaign'          => $this->faker->randomElement($utmCampaigns),
            'utm_content'           => $this->faker->optional(0.4)->lexify('ad-????'),
            'utm_term'              => $this->faker->optional(0.3)->words(2, true),
            'page_url'              => $this->faker->url(),
            'referrer'              => $this->faker->optional(0.5)->url(),
            'consent_accepted_at'   => now(),
            'policy_url'            => $this->faker->url(),
            'ip'                    => $this->faker->ipv4(),
            'user_agent'            => $this->faker->userAgent(),
        ];
    }

    public function forPartner(int $partnerId, ?PartnerWidget $widget = null): static
    {
        return $this->state(fn () => [
            'partner_id' => $partnerId,
            'partner_widget_id' => $widget?->id,
        ]);
    }

    public function withLocation(?int $locationId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => $locationId ?? Location::factory()->create([
                'partner_id' => $attributes['partner_id'] ?? Partner::factory(),
            ])->id,
        ]);
    }

    public function withStatus(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'school_lead_status_id' => $statusId ?? SchoolLeadStatus::systemNewId(),
        ]);
    }
}
