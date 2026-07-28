<?php

return [
    'title' => 'Etkinlik Logları',
    'singular' => 'Denetim Kaydı',
    'subtitle' => 'Yönetimsel, sistem ve kimlik doğrulama olaylarının sistem genelindeki denetim izi.',
    'actions' => [
        'export' => 'Denetim Logunu Dışa Aktar (CSV)',
        'view_full_history' => 'Tüm geçmişi görüntüle',
    ],
    'status_labels' => [
        'no_activity' => 'Bu hesap için henüz kaydedilmiş bir etkinlik yok.',
    ],
    'fields' => [
        'causer' => 'Yapan / Aktör',
        'subject' => 'İlgili Kayıt',
        'module' => 'Modül',
        'action' => 'Eylem',
        'context' => 'Çalışma Zamanı Bağlamı',
        'created_at' => 'Zaman Damgası',
        'changes' => 'Özellik Değişiklikleri',
    ],
];
