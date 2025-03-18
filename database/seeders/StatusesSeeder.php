<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusesSeeder extends Seeder
{
public function run()
{
// Список партнёров:
// если нет таблицы partners, убираем partner_id
$partners = \DB::table('partners')->get();

foreach ($partners as $partner) {
// Создаём системные статусы
$data = [
[
'partner_id' => $partner->id,
'name'       => 'Оплачено',
'icon'       => 'fas fa-circle-check',
'color'      => '#28a745',
'is_system'  => true,
],
[
'partner_id' => $partner->id,
'name'       => 'Учебный день',
'icon'       => 'fas fa-check',
'color'      => '#ffff00',
'is_system'  => true,
],
[
'partner_id' => $partner->id,
'name'       => 'Не был',
'icon'       => 'fas fa-minus',
'color'      => '#ff0000',
'is_system'  => true,
],
// Если хотите "заморозка" считать тоже системным (по ТЗ — частично "заморозка" упомянута
// как пользовательский пример, но можно и иначе) — решайте сами.
// Здесь оставлю как системный пример
// [
//     'partner_id' => $partner->id,
//     'name'       => 'заморозка',
//     'icon'       => 'fas fa-snowflake',
//     'color'      => '#00bfff',
//     'is_system'  => true,
// ],
];

foreach ($data as $item) {
Status::create($item);
}
}

// Если же хотите "заморозку" сделать пользовательским статусом по умолчанию
// (как пример), можно вот так:
Status::create([
'partner_id' => null,  // или реальный partner_id
'name'       => 'заморозка',
'icon'       => 'fas fa-snowflake',
'color'      => '#00bfff',
'is_system'  => false,
]);
}
}
