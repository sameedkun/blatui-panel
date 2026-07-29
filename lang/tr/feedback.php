<?php

return [
    'title' => 'Geri Bildirim',
    'singular' => 'Geri Bildirim Gönderimi',
    'subtitle' => 'Kullanıcılar ve ziyaretçiler tarafından gönderilen geri bildirimleri inceleyin.',

    'fields' => [
        'from' => 'Gönderen',
        'subject' => 'Konu',
        'type' => 'Tür',
        'status' => 'Durum',
        'submitted' => 'Gönderilme',
        'provided_email' => 'Sağlanan E-posta Adresi',
    ],

    'actions' => [
        'view' => 'Görüntüle',
        'mark_read' => 'Okundu İşaretle',
        'mark_as_read' => 'Okundu Olarak İşaretle',
        'resolve' => 'Çöz',
        'mark_resolved' => 'Çözüldü İşaretle',
        'ignore' => 'Yok Say',
        'ignore_feedback' => 'Geri Bildirimi Yok Say',
        'reopen' => 'Yeniden Aç',
        'reopen_submission' => 'Gönderimi Yeniden Aç',
        'save_notes' => 'Dahili Notları Kaydet',
        'view_user_profile' => 'Kullanıcı Profilini Görüntüle',
        'view_guest_profile' => 'Ziyaretçi Profilini Görüntüle',
    ],

    'filters' => [
        'search' => 'Konu, mesaj veya e-posta ara...',
        'clear' => 'Filtreleri temizle',
    ],

    'stats' => [
        'total' => 'Toplam Geri Bildirim',
        'total_description' => 'Tüm zamanlardaki gönderimler',
        'new' => 'Yeni',
        'new_description' => 'İnceleme bekliyor',
        'resolved' => 'Çözüldü',
        'resolved_description' => 'İşlenen gönderimler',
        'anonymous' => 'Anonim',
        'anonymous_description' => 'Hesap olmadan gönderildi',
    ],

    'empty' => [
        'feedback' => 'Geri bildirim bulunamadı.',
        'subject' => 'Konu yok',
        'contact_email' => 'İletişim e-postası sağlanmadı.',
    ],

    'show' => [
        'submitted' => 'Gönderildi',
        'submitted_format' => 'D MMM YYYY [saat] HH:mm',
        'feedback_number' => 'Geri Bildirim #:id',
        'message_title' => 'Kullanıcı Mesajı',
        'message_description' => 'Gönderilen geri bildirimin tüm ayrıntıları.',
        'notes_title' => 'Dahili Yönetici Notları',
        'notes_description' => 'Özel ekip notları — gönderen tarafından hiçbir zaman görülmez.',
        'notes_placeholder' => 'Dahili notlar, çözüm ayrıntıları veya personel inceleme kayıtları ekleyin...',
        'submitter_title' => 'Gönderen Bilgileri',
        'submitter_description' => 'Hesap ayrıntıları ve kaynak.',
        'anonymous_submission' => 'Anonim Gönderim',
        'matching_account' => 'Eşleşen Hesap Bulundu',
        'controls_title' => 'Hızlı Durum Denetimleri',
        'controls_description' => 'Gönderim durumunu güncelleyin.',
    ],

    'validation' => [
        'admin_notes_invalid' => 'Dahili notlar metin olmalıdır.',
        'admin_notes_max' => 'Dahili notlar :max karakterden uzun olamaz.',
    ],

    'validation_attributes' => [
        'admin_notes' => 'dahili notlar',
    ],

    'toasts' => [
        'marked_read' => 'Okundu olarak işaretlendi.',
        'resolved' => 'Geri bildirim çözüldü.',
        'ignored' => 'Geri bildirim yok sayıldı.',
        'reopened' => 'Geri bildirim yeniden açıldı.',
        'notes_saved' => 'Notlar kaydedildi.',
    ],
];
