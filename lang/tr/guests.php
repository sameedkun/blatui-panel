<?php

return [
    'title' => 'Ziyaretçiler',
    'singular' => 'Ziyaretçi',
    'subtitle' => 'Geçici ziyaretçi hesaplarını, dönüşümleri ve hesap birleştirmelerini yönetin.',
    'subtitle_form' => 'Geçici ziyaretçi hesaplarını ve yaşam döngülerini yönetin.',
    'price_option' => ':currency :amount / :interval',

    'actions' => [
        'convert' => 'Kullanıcıya Dönüştür',
        'merge' => 'Mevcut Hesaba Birleştir',
        'purge' => 'Ziyaretçiyi Temizle',
        'ban' => 'Yasakla',
        'unban' => 'Yasağı Kaldır',
        'delete' => 'Sil',
        'restore' => 'Geri Yükle',
        'force_delete' => 'Kalıcı Olarak Sil',
        'view_profile' => 'Profili görüntüle',
        'assign_plan' => 'Plan Ata / Değiştir',
        'cancel_subscription' => 'Aboneliği İptal Et',
        'reactivate_subscription' => 'Aboneliği Yeniden Etkinleştir',
        'cancel_immediately' => 'Hemen İptal Et',
        'cancel_at_period_end' => 'Dönem Sonunda İptal Et',
        'clear_selection' => 'Seçimi temizle',
    ],

    'tabs' => [
        'overview' => 'Genel Bakış',
        'subscriptions' => 'Abonelikler',
        'activity' => 'Etkinlik',
    ],

    'overview' => [
        'general' => 'Genel',
        'dates' => 'Tarihler',
    ],

    'billing_intervals' => [
        'day' => ':count gün',
        'week' => ':count hafta',
        'month' => ':count ay',
        'year' => ':count yıl',
    ],

    'stats' => [
        'total_guests' => 'Toplam Ziyaretçi',
        'all_registered_accounts' => 'Kayıtlı tüm ziyaretçi hesapları',
        'active' => 'Aktif',
        'not_banned' => 'Yasaklanmamış',
        'banned' => 'Yasaklı',
        'banned_accounts' => 'Yasaklı hesaplar',
        'new_this_month' => 'Bu Ay Yeni',
        'joined_this_month' => 'Bu ay katılanlar',
    ],

    'filters' => [
        'status' => 'Durum',
        'registered' => 'Kayıt Tarihi',
        'registered_from' => 'Başlangıç tarihi',
        'registered_to' => 'Bitiş tarihi',
        'active' => 'Aktif',
        'banned' => 'Yasaklı',
    ],

    'fields' => [
        'name' => 'Ziyaretçi Adı',
        'email' => 'E-posta',
        'status' => 'Durum',
        'created_at' => 'İlk Görülme',
        'registered' => 'Kayıt Tarihi',
        'last_login' => 'Son Giriş',
        'guest_id' => 'Ziyaretçi ID',
        'full_name' => 'Ad Soyad',
        'external_id' => 'Harici ID',
        'plan' => 'Plan',
    ],

    'status_labels' => [
        'active' => 'Aktif',
        'banned' => 'Yasaklı',
        'deleted' => 'Silinmiş',
        'never' => 'Hiçbir zaman',
        'no_guests_found' => 'Ziyaretçi bulunamadı.',
        'clear_filters' => 'Filtreleri temizle',
        'selected' => 'seçildi',
        'free' => 'Ücretsiz',
        'coming_soon' => 'Yakında',
    ],

    'defaults' => [
        'ban_reason' => 'Yönetici tarafından yasaklandı.',
    ],

    'lifecycle_states' => [
        'active' => 'aktif',
        'pending' => 'silinmeyi bekliyor',
        'trashed' => 'silinmiş',
    ],

    'errors' => [
        'action_unavailable' => 'Hesap :state durumundayken bu işlem kullanılamaz.',
    ],

    'placeholders' => [
        'email' => 'siz@example.com',
        'full_name' => 'Ad soyad',
    ],

    'validation' => [
        'plan_required' => 'Bir plan seçin.',
        'price_required' => 'Bir fiyat seçin.',
        'convert_email_required' => 'Bir e-posta adresi girin.',
        'convert_email_email' => 'Geçerli bir e-posta adresi girin.',
        'convert_email_unique' => 'Bu e-posta adresi zaten kullanılıyor.',
        'merge_destination_required' => 'Bir hedef hesap seçin.',
        'merge_reason_required' => 'Birleştirme sebebini girin.',
    ],

    'dialogs' => [
        'ban_guest' => 'Ziyaretçiyi Yasakla',
        'ban_guest_desc' => 'İsteğe bağlı olarak bir sebep belirtin. Varsayılan olarak "Yönetici tarafından yasaklandı" yazılır.',
        'ban_reason_placeholder' => 'Yasaklama sebebi (isteğe bağlı)',
        'delete_guest' => 'Ziyaretçiyi Sil',
        'delete_guest_desc' => 'Bu işlem ziyaretçiyi ve ilişkili tüm verileri kalıcı olarak siler. Bu işlem geri alınamaz.',
        'restore_guest' => 'Ziyaretçiyi Geri Yükle',
        'restore_guest_desc' => 'Bu işlem ziyaretçinin hesabını geri yükleyecektir.',
        'force_delete_guest' => 'Kalıcı Olarak Sil',
        'force_delete_guest_desc' => 'Bu işlem geri alınamaz. Ziyaretçi ve ilişkili tüm veriler kalıcı olarak silinecektir.',
        'convert_title' => 'Kullanıcıya Dönüştür',
        'convert_desc' => 'Bu ziyaretçi yerinde gerçek bir uygulama kullanıcısı olur — aynı hesap, aynı geçmiş. Kendi kimlik bilgilerini oluşturmak için bir şifre sıfırlama bağlantısı alacaklar.',
        'mark_email_verified' => 'E-postayı doğrulanmış olarak işaretle',
        'mark_email_verified_help' => 'Doğrulama e-postasını atlar — yalnızca yönetici bu adresin hesap sahibine ait olduğunu doğruladığında kullanın.',
        'merge_title' => 'Mevcut Hesaba Birleştir',
        'merge_desc' => 'Bu ziyaretçinin kimliğini başka bir uygulama hesabına birleştirir. Ziyaretçi kaydı kalıcı olarak kaldırılır; hedef hesap kalır. Bu işlem geri alınamaz.',
        'merge_destination' => 'Hedef hesap',
        'merge_search_placeholder' => 'Kullanıcıları ad veya e-postaya göre ara...',
        'merge_reason' => 'Sebep',
        'merge_reason_placeholder' => 'Bunlar neden aynı kişi?',
        'no_candidates_found' => 'Uygulama kullanıcısı bulunamadı.',
        'change_destination' => 'Değiştir',

        // Bulk dialogs
        'bulk_ban_title' => ':count Ziyaretçiyi Yasakla',
        'bulk_unban_title' => ':count Ziyaretçinin Yasağını Kaldır',
        'bulk_unban_desc' => 'Bu, seçilen tüm ziyaretçilerin yasağını kaldıracaktır.',
        'bulk_delete_title' => ':count Ziyaretçiyi Sil',
        'bulk_delete_desc' => 'Seçilen tüm ziyaretçiler ve verileri kalıcı olarak silinecektir. Bu işlem geri alınamaz.',
        'bulk_restore_title' => ':count Ziyaretçiyi Geri Yükle',
        'bulk_restore_desc' => 'Seçilen tüm silinmiş ziyaretçiler geri yüklenecektir.',
        'bulk_force_delete_title' => ':count Ziyaretçiyi Kalıcı Olarak Sil',
        'bulk_force_delete_desc' => 'Bu işlem geri alınamaz. Seçilen tüm ziyaretçiler kalıcı olarak silinecektir.',
    ],

    'toasts' => [
        'guest_banned' => ':name ziyaretçisi yasaklandı.',
        'guest_unbanned' => ':name yasağı kaldırıldı.',
        'guest_deleted' => ':name kalıcı olarak silindi.',
        'guest_restored' => ':name geri yüklendi.',
        'guest_permanently_deleted' => ':name kalıcı olarak silindi.',
        'guest_converted' => ':name bir uygulama kullanıcısına dönüştürüldü.',
        'guest_merged' => ':guest kullanıcısı :destination hesabına birleştirildi.',
        'bulk_banned' => ':count ziyaretçi yasaklandı.',
        'bulk_unbanned' => ':count ziyaretçinin yasağı kaldırıldı.',
        'bulk_deleted' => ':count ziyaretçi kalıcı olarak silindi.',
        'bulk_restored' => ':count ziyaretçi geri yüklendi.',
        'bulk_permanently_deleted' => ':count ziyaretçi kalıcı olarak silindi.',
        'no_active_subscription' => 'Bu ziyaretçinin aktif aboneliği yok.',
        'plan_assigned' => ':name artık :plan planında.',
        'subscription_cancelled_immediately' => ':plan aboneliği hemen iptal edildi.',
        'subscription_cancelled_period_end' => ':plan aboneliği :date tarihinde sona erecek.',
        'subscription_reactivated' => ':plan aboneliği yeniden etkinleştirildi.',
        'subscription_cannot_reactivate' => 'Bu ziyaretçinin yeniden etkinleştirilebilecek iptal edilmiş bir aboneliği yok.',
    ],
];
