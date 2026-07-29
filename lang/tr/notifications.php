<?php

return [
    'title' => 'Bildirimler',
    'singular' => 'Bildirim',
    'subtitle' => 'OneSignal aracılığıyla abone olan tüm cihazlara anlık bildirim yayınlayın.',

    'actions' => [
        'create' => 'Bildirim Oluştur',
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
        'notification' => 'Bildirim',
        'title' => 'Başlık',
        'message' => 'Mesaj',
        'type' => 'Tür',
        'status' => 'Durum',
        'push_status' => 'Anlık Bildirim Durumu',
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
        'total' => 'Toplam Bildirim',
        'total_description' => 'Tüm zamanlardaki yayınlar',
        'sent' => 'Gönderildi',
        'sent_description' => 'OneSignal’a teslim edildi',
        'failed' => 'Başarısız',
        'failed_description' => 'Yeniden denenmesi gerekiyor',
        'drafts' => 'Taslaklar',
        'drafts_description' => 'Henüz gönderilmedi',
    ],

    'empty' => 'Bildirim bulunamadı.',

    'form' => [
        'create_title' => 'Bildirim Oluştur',
        'edit_title' => 'Bildirimi Düzenle',
        'create_description' => 'Abone olan tüm cihazlara yayınlanacak bir anlık bildirim oluşturun.',
        'edit_description' => 'Aşağıdaki bildirim içeriğini güncelleyin.',
        'breadcrumb_create' => 'Oluştur',
        'breadcrumb_edit' => 'Düzenle',
        'title_placeholder' => 'ör. Yeni özellik kullanıma sunuldu!',
        'message_placeholder' => 'Alıcılara gösterilecek bildirim metnini yazın...',
        'link_description' => 'Bildirime dokunulduğunda açılır (isteğe bağlı).',
        'send_now' => 'Anlık bildirimi şimdi gönder',
        'send_now_description' => 'Daha sonra gönderebileceğiniz bir taslak olarak kaydetmek için işareti kaldırın.',
        'resend_after_update' => 'Kaydettikten sonra anlık bildirimi yeniden gönder',
        'resend_after_update_description' => 'Güncellenen içeriği tüm cihazlara yeniden yayınlar. Yalnızca kaydı güncellemek için işareti kaldırın.',
        'saving' => 'Kaydediliyor...',
        'creating' => 'Oluşturuluyor...',
    ],

    'dialogs' => [
        'delete_title' => 'Bildirimi Sil',
        'delete_description' => 'Bu işlem bildirimi kalıcı olarak siler. Bu işlem geri alınamaz.',
        'bulk_delete_title' => ':count Bildirimi Sil',
        'bulk_delete_description' => 'Bu işlem seçilen tüm bildirimleri kalıcı olarak siler. Bu işlem geri alınamaz.',
        'status_title' => 'Anlık Bildirim Durumu',
        'watching' => 'Durum güncellemesi bekleniyor...',
    ],

    'status_details' => [
        'date_format' => 'D MMM YYYY [saat] HH:mm',
    ],

    'validation' => [
        'title_required' => 'Bir bildirim başlığı girin.',
        'title_invalid' => 'Bildirim başlığı metin olmalıdır.',
        'title_max' => 'Bildirim başlığı :max karakterden uzun olamaz.',
        'message_required' => 'Bir bildirim mesajı girin.',
        'message_invalid' => 'Bildirim mesajı metin olmalıdır.',
        'message_max' => 'Bildirim mesajı :max karakterden uzun olamaz.',
        'type_required' => 'Bir bildirim türü seçin.',
        'type_invalid' => 'Geçerli bir bildirim türü seçin.',
        'link_url' => 'Geçerli bir bildirim bağlantısı girin.',
        'link_max' => 'Bildirim bağlantısı :max karakterden uzun olamaz.',
    ],

    'validation_attributes' => [
        'title' => 'bildirim başlığı',
        'message' => 'bildirim mesajı',
        'type' => 'bildirim türü',
        'link' => 'bildirim bağlantısı',
    ],

    'toasts' => [
        'deleted' => ':title silindi.',
        'bulk_deleted' => ':count bildirim silindi.',
        'push_queued' => '“:title” için anlık bildirim kuyruğa alındı.',
        'updated_resend' => 'Bildirim güncellendi — yeniden gönderim kuyruğa alındı.',
        'updated' => 'Bildirim güncellendi.',
        'created_sent' => 'Bildirim oluşturuldu — gönderim kuyruğa alındı.',
        'created_draft' => 'Bildirim taslak olarak oluşturuldu.',
    ],
];
