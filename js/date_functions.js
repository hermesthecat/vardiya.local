// Timestamp'i Türkçe tarih formatına çevirme
function tarihFormatla(timestamp, format = 'kisa') {
    if (!timestamp) return '';
    
    const tarih = new Date(timestamp * 1000);
    
    const aylar = [
        'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
    ];
    
    const gunler = [
        'Pazar', 'Pazartesi', 'Salı', 'Çarşamba',
        'Perşembe', 'Cuma', 'Cumartesi'
    ];

    switch (format) {
        case 'kisa':
            return new Intl.DateTimeFormat('tr-TR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }).format(tarih);
            
        case 'uzun':
            return `${tarih.getDate()} ${aylar[tarih.getMonth()]} ${tarih.getFullYear()}`;
            
        case 'tam':
            return `${gunler[tarih.getDay()]}, ${tarih.getDate()} ${aylar[tarih.getMonth()]} ${tarih.getFullYear()}`;
            
        case 'saat':
            return new Intl.DateTimeFormat('tr-TR', {
                hour: '2-digit',
                minute: '2-digit'
            }).format(tarih);
            
        case 'tam_saat':
            return `${tarih.getDate()} ${aylar[tarih.getMonth()]} ${tarih.getFullYear()} ${new Intl.DateTimeFormat('tr-TR', {
                hour: '2-digit',
                minute: '2-digit'
            }).format(tarih)}`;
    }
}

// Şu anki timestamp'i alma
function simdikiTimestamp() {
    return Math.floor(Date.now() / 1000);
}

// Tarih string'ini timestamp'e çevirme
function tarihToTimestamp(tarihStr) {
    return Math.floor(new Date(tarihStr).getTime() / 1000);
}

// İki tarih arasındaki gün farkını hesaplama
function gunFarkiHesapla(timestamp1, timestamp2) {
    const gunFarki = Math.floor((timestamp2 - timestamp1) / (60 * 60 * 24));
    return Math.abs(gunFarki);
}

// Belirli bir tarihin haftanın hangi günü olduğunu bulma
function haftaninGunu(timestamp) {
    const gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    const tarih = new Date(timestamp * 1000);
    return gunler[tarih.getDay()];
}

// Input için timestamp'i ISO formatına çevirme
function timestampToInputValue(timestamp) {
    const tarih = new Date(timestamp * 1000);
    return tarih.toISOString().split('T')[0];
}

// Tarih inputundan timestamp elde etme
function inputValueToTimestamp(inputValue) {
    if (!inputValue) return null;
    return Math.floor(new Date(inputValue).getTime() / 1000);
}

// Ay başlangıç timestamp'ini alma
function ayBaslangicTimestamp(timestamp) {
    const tarih = new Date(timestamp * 1000);
    tarih.setDate(1);
    tarih.setHours(0, 0, 0, 0);
    return Math.floor(tarih.getTime() / 1000);
}

// Ay bitiş timestamp'ini alma
function ayBitisTimestamp(timestamp) {
    const tarih = new Date(timestamp * 1000);
    tarih.setMonth(tarih.getMonth() + 1);
    tarih.setDate(0);
    tarih.setHours(23, 59, 59, 999);
    return Math.floor(tarih.getTime() / 1000);
} 