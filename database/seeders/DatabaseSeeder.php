<?php

namespace Database\Seeders;

use App\Models\Coffee;
use App\Models\CoffeeVariant;
use App\Models\Recipe;
use App\Models\Review;
use App\Models\RoastBatch;
use App\Models\Roaster;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Noor de Vries', 'Sam Bakker', 'Lotte van Dijk', 'Daan Visser', 'Eva Smit', 'Milan Jansen', 'Isa Meijer', 'Beheer Zetwerk'];
        $users = [];
        foreach ($names as $i => $name) {
            $users[] = User::forceCreate(['name' => $name, 'email' => $i === 7 ? 'beheer@zetwerk.test' : ($i === 0 ? 'noor@zetwerk.test' : 'gebruiker'.$i.'@zetwerk.test'), 'password' => Hash::make('koffietijd'), 'is_admin' => $i === 7, 'email_verified_at' => now()]);
        }
        $roasters = [];
        foreach ([
            ['Bosrand Coffee', 'bosrand', 'Utrecht', '#315447', 'Een kleine branderij aan de rand van de stad. Bosrand zoekt naar heldere, gelaagde koffies en langdurige relaties met producenten. Licht gebrand, met alle ruimte voor het karakter van de boon.', 'Verzonden binnen 1–2 werkdagen', 14],
            ['Aardewerk Roasters', 'aardewerk', 'Eindhoven', '#ad673f', 'Met beide benen op de grond en de neus boven de brander. Aardewerk brandt toegankelijke koffies met veel zoetheid, voor je dagelijkse koffiemoment.', 'Verzonden binnen 2–3 werkdagen', 14],
            ['Groenlicht Coffee', 'groenlicht', 'Rotterdam', '#9b9d61', 'Goede koffie begint met een open verhaal. Groenlicht kiest traceerbare lots en brandt in kleine oplages: van een zachte espresso tot een verrassende filter.', 'Na bestelling gebrand; verzending binnen 3–5 werkdagen', 10],
        ] as [$name,$slug,$city,$color,$story,$policy,$days]) {
            $roasters[] = Roaster::create(['name' => $name, 'slug' => $slug, 'city' => $city, 'color' => $color, 'story' => $story, 'shipping_policy' => $policy, 'max_roast_days' => $days]);
        }
        $rows = [
            [0, 'Hoogland Lot 01', 'hoogland-lot-01', 'Ethiopië', 'Sidama, hoogland', 'Coöperatie Hoogland', 'Heirloom', 'Washed', 'Licht', 'filter', ['Jasmijn', 'Bergamot', 'Perzik'], 1650, '#d6a653', 'Een bloemige, elegante koffie van grote hoogte. Denk aan zachte perzik, een geur van jasmijn en de frisse afdronk van bergamot. Een fijne koffie om rustig mee aan de dag te beginnen.'],
            [0, 'Bergtuin Lot 02', 'bergtuin-lot-02', 'Colombia', 'Huila', 'Finca Bergtuin', 'Caturra', 'Washed', 'Medium-licht', 'espresso', ['Karamel', 'Rode appel', 'Cacao'], 1350, '#a6664b', 'Een ronde espresso met de zoetheid van karamel en een frisse knipoog van rode appel. Comfortabel, maar allesbehalve saai.'],
            [1, 'Vallei Lot 03', 'vallei-lot-03', 'Brazilië', 'Minas Gerais', 'Fazenda Vallei', 'Bourbon', 'Natural', 'Medium', 'espresso', ['Hazelnoot', 'Chocolade', 'Gedroogd fruit'], 1150, '#aaa37d', 'Vol, zacht en vertrouwd. Hazelnoot en chocolade in een romige espresso die ook met melk overeind blijft.'],
            [1, 'Heuvel Lot 04', 'heuvel-lot-04', 'Kenia', 'Nyeri', 'Heuvel washing station', 'SL28 / SL34', 'Washed', 'Licht', 'filter', ['Zwarte bes', 'Citrus', 'Bruine suiker'], 1700, '#b27e83', 'Een levendige filterkoffie met sappige zwarte bes en een lange, zoete afdronk. Deze voorbeeldbatch valt buiten ons versheidsbeleid.'],
            [2, 'Ochtend Lot 05', 'ochtend-lot-05', 'Guatemala', 'Huehuetenango', 'Finca Ochtend', 'Bourbon / Catuai', 'Washed', 'Medium-licht', 'allround', ['Melkchocolade', 'Sinaasappel', 'Amandel'], 1300, '#c78b58', 'Zacht en veelzijdig met een zonnig randje sinaasappel. Lekker als filter én espresso. Deze koffie branden we pas na jouw bestelling.'],
            [2, 'Decaf Lot 06', 'decaf-lot-06', 'Colombia', 'Cauca', 'Coöperatie Avond', 'Castillo', 'Decaf', 'Medium', 'allround', ['Cacao', 'Karamel', 'Rood fruit'], 1400, '#809795', 'Al het koffiegeluk, zonder de cafeïne. Een volle, zoete koffie voor een langzame avond. Tijdelijk uitverkocht.'],
        ];
        foreach ($rows as $i => [$ri,$name,$slug,$country,$region,$producer,$variety,$process,$roast,$method,$notes,$price,$color,$description]) {
            $coffee = Coffee::create(['roaster_id' => $roasters[$ri]->id, 'name' => $name, 'slug' => $slug, 'country' => $country, 'region' => $region, 'producer' => $producer, 'variety' => $variety, 'process' => $process, 'roast' => $roast, 'method' => $method, 'notes' => $notes, 'description' => $description, 'color' => $color, 'roast_to_order' => $i === 4]);
            foreach ([250, 1000] as $grams) {
                CoffeeVariant::create(['coffee_id' => $coffee->id, 'grams' => $grams, 'price_cents' => $grams === 250 ? $price : $price * 3 + 500, 'stock' => $i === 5 ? 0 : 24]);
            }
            if ($i !== 4) {
                RoastBatch::create(['coffee_id' => $coffee->id, 'code' => 'DEMO-LOT-'.($i + 1), 'roasted_at' => today()->subDays($i === 3 ? 35 : ($i === 0 ? 5 : 8)), 'stock' => $i === 5 ? 0 : 48]);
            }
            for ($j = 0; $j < 2; $j++) {
                $espresso = $method === 'espresso' || ($method === 'allround' && $j === 1);
                Recipe::create(['coffee_id' => $coffee->id, 'user_id' => $users[($i + $j) % 7]->id, 'title' => $espresso ? ($j ? 'Zacht & zoet uit de portafilter' : 'Mijn dagelijkse espresso') : ($j ? 'Een langzame zondagochtend' : 'De heldere V60'), 'method' => $espresso ? 'Espresso' : ($j ? 'Aeropress' : 'V60'), 'dose' => $espresso ? 18 : 15, 'water' => $espresso ? 36 : 250, 'temperature' => $espresso ? 93 : 94, 'seconds' => $espresso ? 30 : 180, 'grinder' => $espresso ? 'Eureka Mignon' : 'Comandante C40', 'grind' => $espresso ? '2,5 vanaf nulpunt' : '24 kliks', 'bloom' => $espresso ? null : 45, 'machine' => $espresso ? 'Lelit Anna' : null, 'steps' => $espresso ? "1. Verwarm de machine en spoel de groep.\n2. Maal 18 g koffie, verdeel gelijkmatig en tamp recht.\n3. Zet op 93 °C en stop bij 36 g in ongeveer 30 seconden.\n4. Proef. Pas de maalstand van jouw molen zo nodig aan." : "1. Spoel het filter en verwarm je server.\n2. Maal 15 g koffie op 24 kliks met de Comandante C40.\n3. Schenk 45 g water op 94 °C en laat 40 seconden bloomen.\n4. Schenk rustig op tot 150 g en daarna tot 250 g.\n5. Laat doorlopen. Richttijd: 2:45–3:15. Proef en stel bij."]);
            }
            if ($i < 5) {
                for ($j = 0; $j < ($i < 3 ? 4 : 3); $j++) {
                    Review::create(['coffee_id' => $coffee->id, 'user_id' => $users[$j]->id, 'rating' => [5, 4, 5, 4][$j], 'method' => $method === 'espresso' ? 'Espresso' : 'V60', 'body' => ['Wat een fijne koffie. De zoetheid komt mooi naar voren en de afdronk blijft lekker lang hangen.', 'Mooi in balans. Met iets grover malen kwam het frisse karakter goed naar voren.', 'Een nieuwe favoriet voor mijn ochtendritueel. De smaaknotities zijn goed te herkennen.', 'Een fijne ontdekking. Na een paar dagen rust smaakte deze koffie voor mij het prettigst.'][$j]]);
                }
            }
        }
    }
}
