<?php

// JSON dosyasından verileri okuma
function veriOku() {
    if (!file_exists('personel.json')) {
        return ['personel' => [], 'vardiyalar' => []];
    }
    $json = file_get_contents('personel.json');
    return json_decode($json, true);
}

// JSON dosyasına veri yazma
function veriYaz($data) {
    file_put_contents('personel.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Yeni personel ekleme
function personelEkle($ad, $soyad) {
    $data = veriOku();
    $yeniPersonel = [
        'id' => uniqid(),
        'ad' => $ad,
        'soyad' => $soyad
    ];
    $data['personel'][] = $yeniPersonel;
    veriYaz($data);
}

// Vardiya ekleme
function vardiyaEkle($personelId, $tarih, $vardiyaTuru) {
    $data = veriOku();
    $yeniVardiya = [
        'id' => uniqid(),
        'personel_id' => $personelId,
        'tarih' => $tarih,
        'vardiya_turu' => $vardiyaTuru
    ];
    $data['vardiyalar'][] = $yeniVardiya;
    veriYaz($data);
}

// Personel listesini getirme (select için)
function personelListesiGetir() {
    $data = veriOku();
    $output = '';
    foreach ($data['personel'] as $personel) {
        $output .= sprintf(
            '<option value="%s">%s %s</option>',
            $personel['id'],
            htmlspecialchars($personel['ad']),
            htmlspecialchars($personel['soyad'])
        );
    }
    return $output;
}

// Vardiya listesini getirme
function vardiyaListesiGetir() {
    $data = veriOku();
    $output = '<table border="1">
        <tr>
            <th>Personel</th>
            <th>Tarih</th>
            <th>Vardiya</th>
        </tr>';
    
    foreach ($data['vardiyalar'] as $vardiya) {
        $personel = array_filter($data['personel'], function($p) use ($vardiya) {
            return $p['id'] === $vardiya['personel_id'];
        });
        $personel = reset($personel);
        
        $vardiyaTurleri = [
            'sabah' => 'Sabah (08:00-16:00)',
            'aksam' => 'Akşam (16:00-24:00)',
            'gece' => 'Gece (24:00-08:00)'
        ];
        
        $output .= sprintf(
            '<tr>
                <td>%s %s</td>
                <td>%s</td>
                <td>%s</td>
            </tr>',
            htmlspecialchars($personel['ad']),
            htmlspecialchars($personel['soyad']),
            date('d.m.Y', strtotime($vardiya['tarih'])),
            $vardiyaTurleri[$vardiya['vardiya_turu']]
        );
    }
    
    $output .= '</table>';
    return $output;
} 