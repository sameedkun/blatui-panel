<?php

return [
    'title' => 'Cihazlar',
    'singular' => 'Cihaz',
    'subtitle' => 'Tüm hesaplarda şimdiye kadar kaydedilmiş cihazları yönetin.',

    'actions' => [
        'revoke' => 'Erişimi İptal Et',
        'revoke_short' => 'İptal Et',
        'revoke_all' => 'Tüm Cihazları İptal Et',
        'block' => 'Cihazı Engelle',
        'block_short' => 'Engelle',
        'unblock' => 'Cihaz Engelini Kaldır',
        'unblock_short' => 'Engeli Kaldır',
        'shared_fingerprints' => 'Paylaşılan Parmak İzleri',
        'export_csv' => 'CSV Dışa Aktar',
        'actions' => 'İşlemler',
        'clear_filters' => 'Filtreleri temizle',
    ],

    'fields' => [
        'user' => 'Kullanıcı',
        'device_name' => 'Cihaz Adı',
        'device_type' => 'Cihaz Türü',
        'platform' => 'Platform',
        'ip_address' => 'IP Adresi',
        'last_active' => 'Son Etkinlik',
        'last_seen' => 'Son Görülme',
        'app_version' => 'Uygulama Sürümü',
        'country' => 'Ülke',
        'status' => 'Durum',
        'fingerprint' => 'Parmak İzi',
        'location_ip' => 'Konum / IP',
    ],

    'stats' => [
        'total' => 'Toplam Cihaz',
        'ever_registered' => 'Şimdiye kadar kaydedilen',
        'currently_signed_in' => 'Şu anda oturum açmış',
        'revoked_description' => 'Oturumu kapatılmış, yeniden etkinleşebilir',
        'blocked_description' => 'Giriş yaparak yeniden etkinleşemez',
    ],

    'status' => [
        'active' => 'Aktif',
        'revoked' => 'Erişimi İptal Edilmiş',
        'blocked' => 'Engellenmiş',
        'unnamed_device' => 'Adsız cihaz',
        'unknown_type' => 'Bilinmeyen tür',
        'never_seen' => 'Hiç görülmedi',
        'never' => 'Hiçbir zaman',
        'none_found' => 'Cihaz bulunamadı.',
    ],

    'placeholders' => [
        'fingerprint' => 'Ham istemci değeri',
        'block_reason' => 'Bu cihaz neden engelleniyor?',
    ],

    'dialogs' => [
        'block_title' => 'Cihazı Engelle',
        'block_description' => 'Engellenen cihazın oturumu hemen kapatılır ve tekrar giriş yaparak etkinleştirilemez — engeli yalnızca bir yönetici kaldırabilir.',
        'reason' => 'Sebep',
        'revoke_title' => 'Cihaz Erişimini İptal Et',
        'revoke_description' => 'Bu işlem cihazın oturumunu hemen kapatır. Hesabın cihaz sınırına bağlı olarak cihaz daha sonra tekrar giriş yapabilir.',
    ],

    'validation' => [
        'block_reason_required' => 'Bir cihazı engellemek için sebep gereklidir.',
        'block_reason_min' => 'Sebep en az :min karakter olmalıdır.',
    ],

    'toasts' => [
        'blocked' => ':name engellendi.',
        'unblocked' => ':name cihazının engeli kaldırıldı.',
        'unblocked_description' => 'Cihazın yeniden bağlanması için kullanıcının tekrar giriş yapması gerekir.',
        'revoked' => ':name cihazının erişimi iptal edildi.',
    ],

    'shared' => [
        'title' => 'Paylaşılan Parmak İzleri',
        'description' => 'Birden fazla hesaba bağlı cihaz parmak izleri — hesap paylaşımının veya sahte bir istemcinin işareti olabilir.',
        'accounts' => 'Hesaplar',
        'shared_by' => 'Paylaşanlar',
        'more' => '+:count kişi daha',
        'none_found' => 'Paylaşılan parmak izi bulunamadı.',
    ],

    'csv' => [
        'user' => 'Kullanıcı',
        'name' => 'Ad',
        'platform' => 'Platform',
        'os' => 'İşletim Sistemi',
        'device_type' => 'Cihaz Türü',
        'app_version' => 'Uygulama Sürümü',
        'country' => 'Ülke',
        'ip_address' => 'IP Adresi',
        'status' => 'Durum',
        'last_seen' => 'Son Görülme',
    ],
];
