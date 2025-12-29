<?php

class Captcha
{
    /**
     * Kategori ve İkon Unicode Tanımları (FontAwesome 6 Free)
     * Botların sınıf isimlerini okuyamaması için doğrudan unicode kullanıyoruz.
     */
    private static $categories = [
        'music' => [
            'name_key' => 'cat_music',
            'icons' => ['f001', 'f025', 'f7a6', 'f569', 'f130'] // music, headphones, guitar, drum, microphone
        ],
        'transport' => [
            'name_key' => 'cat_transport',
            'icons' => ['f1b9', 'f207', 'f072', 'f206', 'f21a'] // car, bus, plane, bicycle, ship
        ],
        'animals' => [
            'name_key' => 'cat_animals',
            'icons' => ['f6d3', 'f6be', 'f578', 'f4ba', 'f717'] // dog, cat, fish, dove, spider
        ]
    ];

    /**
     * Oturumu başlatır (Eğer başlamadıysa)
     */
    public static function init()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Yeni bir captcha oluşturur.
     * @return array Frontend için gerekli veriler (Instruction, Grid, CSS)
     */
    public static function generate()
    {
        self::init();

        // Kategorileri al ve rastgele bir hedef seç
        $cats = array_keys(self::$categories);
        $targetCatKey = $cats[array_rand($cats)];
        $targetCat = self::$categories[$targetCatKey];

        // Hedef kategoriden 2 veya 3 ikon seç
        $targetCount = rand(2, 3);
        // array_rand tekil değer dönebilir, array'e zorluyoruz
        $targetIconKeys = (array) array_rand(array_flip($targetCat['icons']), $targetCount);

        // Diğer kategorilerden dolgu ikonlar seç (Toplam 9'a tamamla)
        $otherIconsPool = [];
        foreach ($cats as $cat) {
            if ($cat === $targetCatKey)
                continue;
            foreach (self::$categories[$cat]['icons'] as $icon) {
                $otherIconsPool[] = $icon;
            }
        }

        $fillerCount = 9 - $targetCount;
        $fillerIconKeys = (array) array_rand(array_flip($otherIconsPool), $fillerCount);

        // Tüm ikonları birleştir
        $gameIcons = array_merge($targetIconKeys, $fillerIconKeys);
        shuffle($gameIcons); // Grid için karıştır

        // Frontend verilerini hazırla
        $gridData = [];
        $correctIds = [];
        $cssRules = "";

        foreach ($gameIcons as $unicode) {
            // Güvenlik: Tahmin edilemez ID ve Class isimleri üret
            $id = "icon_" . bin2hex(random_bytes(4));
            $obfuscatedClass = "cpt_" . bin2hex(random_bytes(3));

            // Eğer bu ikon hedef kategorideyse, doğru cevaplar listesine ekle
            if (in_array($unicode, $targetCat['icons'])) {
                $correctIds[] = $id;
            }

            // Frontend'e gidecek obje
            $gridData[] = [
                'id' => $id,
                'class' => $obfuscatedClass
            ];

            // Dinamik CSS (Botların görmemesi için class -> unicode eşleşmesi burada yapılır)
            // Font Awesome 6 Free font ailesini kullanıyoruz.
            $cssRules .= ".$obfuscatedClass::before { content: \"\\$unicode\"; font-family: \"Font Awesome 6 Free\"; font-weight: 900; font-style: normal; }\n";
        }

        // Doğru cevapları Session'a kaydet
        $_SESSION['captcha'] = [
            'correct_ids' => $correctIds,
            'created_at' => time()
        ];

        return [
            'target_key' => $targetCat['name_key'], // Frontend bu anahtarı kullanarak cümleyi kuracak
            'grid' => $gridData,
            'css' => $cssRules
        ];
    }

    /**
     * Kullanıcının cevabını doğrular.
     * @param string $inputMap Virgülle ayrılmış ID listesi (örn: "id1,id3")
     * @return bool
     */
    public static function verify($inputMap)
    {
        self::init();

        if (!isset($_SESSION['captcha']) || !isset($_SESSION['captcha']['correct_ids'])) {
            return false;
        }

        $corrects = $_SESSION['captcha']['correct_ids'];

        // Inputu diziye çevir
        $selected = explode(',', (string) $inputMap);
        $selected = array_filter($selected); // boşlukları temizle

        // Sayı kontrolü
        if (count($selected) !== count($corrects)) {
            unset($_SESSION['captcha']); // Yanlış denemede captcha'yı iptal et
            return false;
        }

        // İçerik kontrolü (Sıralama önemsiz)
        sort($selected);
        sort($corrects);

        if ($selected === $corrects) {
            unset($_SESSION['captcha']); // Başarılı, tekrar kullanılamaz
            return true;
        }

        unset($_SESSION['captcha']); // Başarısız
        return false;
    }
}
