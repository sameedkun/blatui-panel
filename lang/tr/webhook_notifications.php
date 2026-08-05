<?php

return [
    'title' => 'Webhook Bildirimleri',
    'singular' => 'Webhook Bildirimi',
    'subtitle' => 'Abonelik faturalama olayları için sağlayıcılardan alınan ham webhook bildirimleri.',

    'breadcrumbs' => [
        'home' => 'Ana Sayfa',
        'webhook_notifications' => 'Webhook Bildirimleri',
    ],

    'tab' => [
        'label' => 'Webhook Bildirimleri',
    ],

    'actions' => [
        'view' => 'Görüntüle',
        'process' => 'İşle',
        'reprocess' => 'Yeniden İşle',
    ],

    'dialogs' => [
        'redispatch_title' => 'Bu Bildirimi Yeniden Gönder',
        'redispatch_description' => 'Sağlayıcının webhook-alındı olayını bu bildirimin kayıtlı verisiyle yeniden tetikler, sanki az önce tekrar gelmiş gibi. O olaya bağlanmış herhangi bir dinleyici varsa bu bildirim üzerinde çalışır.',
    ],

    'toasts' => [
        'redispatched' => 'Bildirim yeniden gönderildi.',
    ],

    'filters' => [
        'provider' => 'Sağlayıcı',
        'processed' => 'İşlendi',
        'search' => 'İşlem kimliği, ürün kimliği ara...',
        'clear' => 'Filtreleri temizle',
    ],

    'stats' => [
        'total' => 'Toplam',
        'total_description' => 'Bu sağlayıcı için tüm bildirimler',
        'unprocessed' => 'İşlenmedi',
        'unprocessed_description' => 'İşlenmeyi bekliyor',
        'today' => 'Bugün',
        'today_description' => 'Son 24 saatte alınan',
    ],

    'table' => [
        'notification_type' => 'Tür',
        'transaction_id' => 'İşlem Kimliği',
        'product_id' => 'Ürün Kimliği',
        'occurred_at' => 'Gerçekleşme',
        'processed' => 'İşlendi',
    ],

    'detail' => [
        'notification_type' => 'Bildirim Türü',
        'subtype' => 'Alt Tür',
        'transaction_id' => 'İşlem Kimliği',
        'original_transaction_id' => 'Orijinal İşlem Kimliği',
        'product_id' => 'Ürün Kimliği',
        'environment' => 'Ortam',
        'occurred_at' => 'Gerçekleşme Tarihi',
        'processed_at' => 'İşlenme Tarihi',
        'raw_payload' => 'Ham Veri',
    ],

    'empty' => [
        'notifications' => 'Webhook bildirimi bulunamadı.',
        'subscription_notifications' => 'Bu aboneliğe bağlı henüz bir webhook bildirimi yok.',
        'select_provider' => 'Bildirimlerini görmek için bir sağlayıcı seçin.',
    ],

    'values' => [
        'processed' => 'İşlendi',
        'unprocessed' => 'İşlenmedi',
    ],
];
