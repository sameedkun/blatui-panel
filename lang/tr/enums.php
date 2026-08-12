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
        'RevenueCat' => 'RevenueCat',
    ],

    'apple_notification_type' => [
        'ConsumptionRequest' => 'Tüketim Talebi',
        'DidChangeRenewalPref' => 'Yenileme Tercihi Değişti',
        'DidChangeRenewalStatus' => 'Yenileme Durumu Değişti',
        'DidFailToRenew' => 'Yenileme Başarısız',
        'DidRenew' => 'Yenilendi',
        'Expired' => 'Süresi Doldu',
        'ExternalPurchaseToken' => 'Harici Satın Alma Jetonu',
        'GracePeriodExpired' => 'Ek Süre Doldu',
        'OfferRedeemed' => 'Teklif Kullanıldı',
        'OneTimeCharge' => 'Tek Seferlik Ücret',
        'PriceIncrease' => 'Fiyat Artışı',
        'Refund' => 'İade',
        'RefundDeclined' => 'İade Reddedildi',
        'RefundReversed' => 'İade Geri Alındı',
        'RenewalExtended' => 'Yenileme Uzatıldı',
        'RenewalExtension' => 'Yenileme Uzatması',
        'Revoke' => 'İptal Edildi',
        'Subscribed' => 'Abone Oldu',
        'Test' => 'Test Bildirimi',
    ],

    'apple_notification_subtype' => [
        'InitialBuy' => 'İlk Satın Alma',
        'Resubscribe' => 'Yeniden Abonelik',
        'Downgrade' => 'Düşürme',
        'Upgrade' => 'Yükseltme',
        'AutoRenewEnabled' => 'Otomatik Yenileme Açık',
        'AutoRenewDisabled' => 'Otomatik Yenileme Kapalı',
        'Voluntary' => 'Gönüllü',
        'BillingRetry' => 'Faturalama Tekrar Denemesi',
        'PriceIncrease' => 'Fiyat Artışı',
        'GracePeriod' => 'Ek Süre',
        'BillingRecovery' => 'Faturalama Kurtarma',
        'Pending' => 'Beklemede',
        'Accepted' => 'Kabul Edildi',
        'ProductNotForSale' => 'Ürün Satışta Değil',
        'Unreported' => 'Raporlanmadı',
        'Failure' => 'Başarısız',
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
];
