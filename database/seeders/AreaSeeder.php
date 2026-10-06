<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Delivery areas in display order. "Other" must stay last.
     * Spellings vary in English. Edit names in the admin panel if you prefer other spellings.
     */
    public const NAMES = [
        // Original seven (spec 4.15)
        'Olaya',
        'Malaz',
        'Batha',
        'Al Naseem',
        'Sulaimaniyah',
        'Al Yasmin',
        'Al Rawdah',

        // More Riyadh districts
        'Al Wurud',
        'Al Murabba',
        'Al Sahafa',
        'Al Nakheel',
        'Al Malqa',
        'Hittin',
        'Al Aqiq',
        'Al Ghadir',
        'Al Narjis',
        'Al Rabi',
        'Al Falah',
        'Al Mursalat',
        'Al Muruj',
        'Al Rabwa',
        'Al Faisaliyah',
        'Al Maather',
        'Al Mohammadiyah',
        'Al Yarmuk',
        'Al Munsiyah',
        'Al Qadisiyah',
        'Al Hamra',
        'Al Rayyan',
        'Al Nahda',
        'Al Andalus',
        'Al Khaleej',
        'Al Izdihar',
        'Al Sulay',
        'Al Dar Al Baida',
        'Namar',
        'Taibah',
        'Al Aziziyah',
        'Al Shifa',
        'Al Uraija',
        'Al Manar',
        'Ishbiliyah',
        'Qurtubah',
        'Al Wadi',
        'Al Arid',
        'Diriyah',
        'Al Suwaidi',
        'Al Zahra',
        'Al Futah',
        'Umm Al Hamam',
        'Al Salam',
        'Al Rimal',
        'Al Rafiah',
        'Al Nuzhah',
        'Al Wizarat',
        'Al Rawabi',
        'KAFD',
        'Diplomatic Quarter',

        'Other',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $i => $name) {
            Area::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true],
            );
        }
    }
}