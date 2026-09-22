<?php

return [
    'title' => 'Duyurular',
    'singular' => 'Duyuru',
    'subtitle' => 'OneSignal aracılığıyla abone olan tüm cihazlara anlık duyuru yayınlayın.',

    'actions' => [
        'create' => 'Duyuru Oluştur',
        'edit' => 'Düzenle',
        'delete' => 'Sil',
        'resend' => 'Yeniden Gönder',
        'retry' => 'Yeniden Dene',
        'view_status' => 'Durumu Görüntüle',
        'save_changes' => 'Değişiklikleri Kaydet',
        'create_send' => 'Oluştur ve Gönder',
        'save_draft' => 'Taslak Olarak Kaydet',
        'cancel' => 'İptal',
        'close' => 'Kapat',
        'clear_selection' => 'Seçimi temizle',
        'selected' => ':count seçildi',
    ],

    'fields' => [
        'announcement' => 'Duyuru',
        'title' => 'Başlık',
        'message' => 'Mesaj',
        'type' => 'Tür',
        'status' => 'Durum',
        'push_status' => 'Anlık Duyuru Durumu',
        'link' => 'Bağlantı',
        'created' => 'Oluşturulma',
        'sent_at' => 'Gönderilme Zamanı',
        'onesignal_id' => 'OneSignal Kimliği',
        'error' => 'Hata',
    ],

    'filters' => [
        'search' => 'Başlık veya mesaj ara...',
        'clear' => 'Filtreleri temizle',
    ],

    'stats' => [
        'total' => 'Toplam Duyuru',
        'total_description' => 'Tüm zamanlardaki yayınlar',
        'sent' => 'Gönderildi',
        'sent_description' => 'OneSignal’a teslim edildi',
        'failed' => 'Başarısız',
        'failed_description' => 'Yeniden denenmesi gerekiyor',
        'drafts' => 'Taslaklar',
        'drafts_description' => 'Henüz gönderilmedi',
    ],

    'empty' => 'Duyuru bulunamadı.',

    'form' => [
        'create_title' => 'Duyuru Oluştur',
        'edit_title' => 'Duyuruyu Düzenle',
        'create_description' => 'Abone olan tüm cihazlara yayınlanacak bir anlık duyuru oluşturun.',
        'edit_description' => 'Aşağıdaki duyuru içeriğini güncelleyin.',
        'breadcrumb_create' => 'Oluştur',
        'breadcrumb_edit' => 'Düzenle',
        'title_placeholder' => 'ör. Yeni özellik kullanıma sunuldu!',
        'message_placeholder' => 'Alıcılara gösterilecek duyuru metnini yazın...',
        'link_description' => 'Duyuruya dokunulduğunda açılır (isteğe bağlı).',
        'send_now' => 'Anlık duyuruyu şimdi gönder',
        'send_now_description' => 'Daha sonra gönderebileceğiniz bir taslak olarak kaydetmek için işareti kaldırın.',
        'resend_after_update' => 'Kaydettikten sonra anlık duyuruyu yeniden gönder',
        'resend_after_update_description' => 'Güncellenen içeriği tüm cihazlara yeniden yayınlar. Yalnızca kaydı güncellemek için işareti kaldırın.',
        'saving' => 'Kaydediliyor...',
        'creating' => 'Oluşturuluyor...',
    ],

    'dialogs' => [
        'delete_title' => 'Duyuruyu Sil',
        'delete_description' => 'Bu işlem duyuruyu kalıcı olarak siler. Bu işlem geri alınamaz.',
        'bulk_delete_title' => ':count Duyuruyu Sil',
        'bulk_delete_description' => 'Bu işlem seçilen tüm duyuruları kalıcı olarak siler. Bu işlem geri alınamaz.',
        'status_title' => 'Anlık Duyuru Durumu',
        'watching' => 'Durum güncellemesi bekleniyor...',
    ],

    'status_details' => [
        'date_format' => 'D MMM YYYY [saat] HH:mm',
    ],

    'validation' => [
        'title_required' => 'Bir duyuru başlığı girin.',
        'title_invalid' => 'Duyuru başlığı metin olmalıdır.',
        'title_max' => 'Duyuru başlığı :max karakterden uzun olamaz.',
        'message_required' => 'Bir duyuru mesajı girin.',
        'message_invalid' => 'Duyuru mesajı metin olmalıdır.',
        'message_max' => 'Duyuru mesajı :max karakterden uzun olamaz.',
        'type_required' => 'Bir duyuru türü seçin.',
        'type_invalid' => 'Geçerli bir duyuru türü seçin.',
        'link_url' => 'Geçerli bir duyuru bağlantısı girin.',
        'link_max' => 'Duyuru bağlantısı :max karakterden uzun olamaz.',
    ],

    'validation_attributes' => [
        'title' => 'duyuru başlığı',
        'message' => 'duyuru mesajı',
        'type' => 'duyuru türü',
        'link' => 'duyuru bağlantısı',
    ],

    'toasts' => [
        'deleted' => ':title silindi.',
        'bulk_deleted' => ':count duyuru silindi.',
        'push_queued' => '“:title” için anlık duyuru kuyruğa alındı.',
        'updated_resend' => 'Duyuru güncellendi — yeniden gönderim kuyruğa alındı.',
        'updated' => 'Duyuru güncellendi.',
        'created_sent' => 'Duyuru oluşturuldu — gönderim kuyruğa alındı.',
        'created_draft' => 'Duyuru taslak olarak oluşturuldu.',
    ],
];
