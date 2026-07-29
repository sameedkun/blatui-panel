<?php

return [
    'user_type' => [
        'App' => 'Uygulama Kullanıcısı',
        'Staff' => 'Personel',
        'Guest' => 'Ziyaretçi',
    ],

    'subscription_status' => [
        'Trialing' => 'Deneme',
        'Active' => 'Aktif',
        'Grace' => 'Ek Süre',
        'Cancelled' => 'İptal Edildi',
        'Expired' => 'Süresi Doldu',
        'Failed' => 'Başarısız',
    ],

    'ticket_status' => [
        'Open' => 'Açık',
        'Pending' => 'Beklemede',
        'Resolved' => 'Çözüldü',
        'Closed' => 'Kapalı',
    ],

    'ticket_priority' => [
        'Low' => 'Düşük',
        'Medium' => 'Orta',
        'High' => 'Yüksek',
        'Urgent' => 'Acil',
    ],

    'feedback_status' => [
        'New' => 'Yeni',
        'Read' => 'Okundu',
        'Resolved' => 'Çözüldü',
        'Ignored' => 'Yok Sayıldı',
    ],

    'feedback_type' => [
        'General' => 'Genel',
        'Bug' => 'Hata',
        'Feature' => 'Özellik İsteği',
        'Complaint' => 'Şikâyet',
        'Other' => 'Diğer',
    ],

    'notification_push_status' => [
        'Draft' => 'Taslak',
        'Pending' => 'Beklemede',
        'Sent' => 'Gönderildi',
        'Failed' => 'Başarısız',
    ],

    'notification_type' => [
        'General' => 'Genel',
        'Announcement' => 'Duyuru',
        'Promotional' => 'Tanıtım',
        'Alert' => 'Uyarı',
    ],

    'device_type' => [
        'Mobile' => 'Mobil',
        'Tablet' => 'Tablet',
        'Desktop' => 'Masaüstü',
        'Web' => 'Web',
    ],

    'billing_interval' => [
        'Day' => 'Gün',
        'Week' => 'Hafta',
        'Month' => 'Ay',
        'Year' => 'Yıl',
    ],

    'billing_interval_count' => [
        'Day' => ':count Gün',
        'Week' => ':count Hafta',
        'Month' => ':count Ay',
        'Year' => ':count Yıl',
    ],

    'payment_provider' => [
        'Local' => 'Yerel',
        'Stripe' => 'Stripe',
        'AppStore' => 'App Store',
        'PlayStore' => 'Play Store',
        'Oxapay' => 'OxaPay',
    ],

    'cancelled_by' => [
        'User' => 'Kullanıcı',
        'Admin' => 'Yönetici',
        'System' => 'Sistem',
    ],

    'receipt_type' => [
        'Initial' => 'İlk Ödeme',
        'Renewal' => 'Yenileme',
        'Restore' => 'Geri Yükleme',
        'Refund' => 'İade',
        'Cancellation' => 'İptal',
    ],

    'device_type' => [
        'Mobile' => 'Mobil',
        'Tablet' => 'Tablet',
        'Desktop' => 'Masaüstü',
        'Web' => 'Web',
    ],
];
