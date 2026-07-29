<?php

return [
    'title' => 'Engellenen IP’ler',
    'singular' => 'Engellenen IP',
    'subtitle' => 'Bir IP’yi isteğe bağlı sona erme ve isabet takibiyle genel olarak veya tek bir kullanıcı için engelleyin.',

    'actions' => [
        'create' => 'IP Engelle',
        'edit' => 'Düzenle',
        'delete' => 'Sil',
        'purge_expired' => '{1} Süresi Dolan :count Kaydı Sil|[2,*] Süresi Dolan :count Kaydı Sil',
        'inspect' => 'Bu IP’nin Arkasında Kim Var',
        'clear_selection' => 'Seçimi temizle',
        'selected' => ':count seçildi',
        'save_changes' => 'Değişiklikleri Kaydet',
        'cancel' => 'İptal',
        'close' => 'Kapat',
    ],

    'fields' => [
        'ip_address' => 'IP Adresi',
        'scope' => 'Kapsam',
        'reason' => 'Sebep',
        'created_by' => 'Oluşturan',
        'hits' => 'İsabetler',
        'last_hit' => 'Son İsabet',
        'expires' => 'Sona Erme',
        'expires_at' => 'Sona Erme Tarihi',
        'user_email' => 'Kullanıcı E-postası',
    ],

    'scopes' => [
        'global' => 'Genel',
        'global_block' => 'Genel Engel',
        'global_every_user' => 'Genel (tüm kullanıcılar)',
        'per_user' => 'Kullanıcıya Özel',
        'per_user_block' => 'Kullanıcıya Özel Engel',
    ],

    'filters' => [
        'expiry' => 'Sona Erme',
        'search' => 'IP adresi ara...',
        'clear' => 'Filtreleri temizle',
    ],

    'stats' => [
        'total' => 'Toplam Engel',
        'total_description' => 'Tüm kurallar',
        'active' => 'Aktif',
        'active_description' => 'Şu anda uygulanıyor',
        'expired' => 'Süresi Dolmuş',
        'expired_description' => 'Temizlenebilir',
        'global' => 'Genel',
        'global_description' => 'Tüm kullanıcıları engeller',
    ],

    'status' => [
        'expired' => 'Süresi Dolmuş',
        'not_expired' => 'Süresi Dolmamış',
        'permanent' => 'Kalıcı',
        'never' => 'Hiçbir zaman',
        'system' => 'Sistem',
        'unknown_user' => 'Bilinmeyen kullanıcı',
        'unknown_platform' => 'Bilinmeyen platform',
        'user_number' => 'Kullanıcı #:id',
    ],

    'empty' => [
        'blocked_ips' => 'Engellenen IP bulunamadı.',
        'devices' => 'Bu IP’de görülen bir cihaz yok.',
        'users' => 'Uygulama kullanıcısı bulunamadı.',
        'users_matching' => '“:search” ile eşleşen uygulama kullanıcısı bulunamadı.',
    ],

    'form' => [
        'create_title' => 'IP Adresini Engelle',
        'edit_title' => 'Engeli Düzenle',
        'create_description' => 'Tek bir IP adresinden gelen trafiği genel olarak veya bir hesap için engelleyin.',
        'edit_description' => 'Bu engelin sebebini veya sona erme tarihini güncelleyin.',
        'breadcrumb_create' => 'IP Engelle',
        'breadcrumb_edit' => 'Düzenle',
        'change_user' => 'Kullanıcıyı Değiştir',
        'search_users' => 'Uygulama kullanıcılarını ad veya e-postayla ara...',
        'reason_placeholder' => 'Bu IP neden engelleniyor?',
        'global_warning_title' => 'Bu, IP’yi kullanan tüm hesapları engeller',
        'distinct_accounts' => 'Son 30 günde bu IP’de :count farklı hesap görüldü.',
        'carrier_nat_warning' => 'Bu, paylaşılan bir operatör IP’sine (örneğin mobil ağ NAT’ına) benziyor — engellemek birbiriyle ilgisiz meşru kullanıcıların erişimini kesebilir.',
        'global_confirmation' => 'Bu IP’yi tüm kullanıcılar için engellemek istediğimi anlıyorum.',
        'permanent' => 'Kalıcı (sona ermez)',
        'expires_description' => 'Varsayılan süre 7 gündür — kalıcı engeller birikir, bu nedenle bunu bilinçli olarak seçin.',
        'saving' => 'Kaydediliyor...',
        'blocking' => 'Engelleniyor...',
    ],

    'dialogs' => [
        'delete_title' => 'Engeli Sil',
        'delete_description' => 'Bu işlem engelin uygulanmasını hemen durdurur. Bu IP’den gelen trafiğe yeniden izin verilir. Bu işlem geri alınamaz.',
        'expired_title' => 'Süresi Dolan :count Engeli Sil',
        'expired_description' => 'Sona erme tarihi geçmiş tüm engeller kalıcı olarak silinir. Bu işlem geri alınamaz.',
        'bulk_delete_title' => ':count Engeli Sil',
        'bulk_delete_description' => 'Seçilen tüm engeller kalıcı olarak silinir. Bu işlem geri alınamaz.',
        'delete_all' => 'Tümünü Sil',
    ],

    'activity' => [
        'summary' => 'Son 30 günde bu IP’de :count farklı hesap görüldü.',
        'device_details' => ':device · :platform · son görülme :last_seen',
    ],

    'validation' => [
        'ip_required' => 'Bir IP adresi girin.',
        'ip_invalid' => 'Geçerli bir IPv4 veya IPv6 adresi girin.',
        'scope_required' => 'Bir engel kapsamı seçin.',
        'scope_invalid' => 'Geçerli bir engel kapsamı seçin.',
        'user_email_required' => 'Kullanıcıya özel engel için bir kullanıcı seçin.',
        'user_email_invalid' => 'Geçerli bir kullanıcı e-posta adresi girin.',
        'reason_invalid' => 'Sebep metin olmalıdır.',
        'reason_max' => 'Sebep :max karakterden uzun olamaz.',
        'permanent_invalid' => 'Kalıcı seçimi geçersiz.',
        'expires_required' => 'Bir sona erme tarihi girin veya kalıcı engeli seçin.',
        'expires_invalid' => 'Geçerli bir sona erme tarihi girin.',
        'expires_future' => 'Sona erme tarihi gelecekte olmalıdır.',
        'confirm_global' => 'Bu IP’deki tüm kullanıcıları engellemeden önce genel kapsam uyarısını onaylayın.',
        'user_not_found' => 'Bu e-posta adresine sahip bir hesap bulunamadı.',
        'duplicate_global' => 'Bu IP için zaten genel bir engel var.',
        'duplicate_user' => 'Bu IP, bu kullanıcı için zaten engellenmiş.',
    ],

    'validation_attributes' => [
        'ip_address' => 'IP adresi',
        'scope' => 'kapsam',
        'user_email' => 'kullanıcı e-postası',
        'reason' => 'sebep',
        'permanent' => 'kalıcı durumu',
        'expires_at' => 'sona erme tarihi',
    ],

    'toasts' => [
        'created' => 'IP adresi engellendi.',
        'updated' => 'Engel güncellendi.',
        'deleted' => ':ip üzerindeki engel kaldırıldı.',
        'bulk_deleted' => ':count engel silindi.',
        'expired_deleted' => 'Süresi dolan :count engel silindi.',
    ],
];
