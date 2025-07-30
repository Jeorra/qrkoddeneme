Gelişmiş PHP QR Kod Giriş Projesi - Kurulum ve Çalıştırma Yönergesi
===================================================================

Hoş geldiniz! Bu belge, PHP, WebSockets ve Redis kullanarak oluşturulmuş modern bir QR kod ile giriş sistemini
kendi bilgisayarınızda nasıl kurup çalıştıracağınızı adım adım anlatmaktadır.

---

**BÖLÜM 1: GEREKLİ YAZILIMLAR (ÖN GEREKSİNİMLER)**

Bu projeyi çalıştırmak için üç ana yazılıma ihtiyacınız olacak:

1.  **XAMPP:** Bilgisayarınıza Apache (Web Sunucusu), MySQL (Veritabanı Sunucusu) ve PHP (Programlama Dili) kuran
    ücretsiz bir pakettir. Projemizin temelini bu oluşturur.
    *   İndirme Linki: https://www.apachefriends.org/tr/index.html

2.  **Composer:** PHP için bir bağımlılık yöneticisidir. Projemizin ihtiyaç duyduğu harici kütüphaneleri
    (WebSocket sunucusu, QR kod üreticisi vb.) otomatik olarak indirip kurmamızı sağlar.
    *   İndirme Linki: https://getcomposer.org/

3.  **Redis for Windows:** Çok hızlı bir anahtar-değer veritabanıdır. Bu projede, web sunucusu ile WebSocket sunucusu
    arasında anlık mesajlaşmayı (bildirimleri) sağlamak için "haberci" (Pub/Sub) olarak kullanılır.
    *   İndirme Linki: https://github.com/tporadowski/redis/releases (En son sürümün altındaki .zip dosyasını indirin)

---

**BÖLÜM 2: KURULUM ADIMLARI**

**Adım 2.1: Proje Dosyaları ve PHP Bağımlılıkları**

1.  Proje dosyalarını bilgisayarınızda `C:\xampp\htdocs\qrkod` klasörüne kopyalayın.
2.  Bir komut istemi (CMD veya PowerShell) açın ve proje klasörüne gidin. Şu komutu yazın:
    ```
    cd C:\xampp\htdocs\qrkod
    ```
3.  Aşağıdaki komutu çalıştırarak Composer'ın gerekli tüm PHP kütüphanelerini indirmesini sağlayın:
    *Açıklama: Bu komut, `composer.json` dosyasını okur ve projede listelenen tüm paketleri `vendor` adlı bir klasöre yükler.*
    ```
    composer install
    ```

**Adım 2.2: Veritabanı ve Test Kullanıcısı**

1.  XAMPP Kontrol Paneli'ni açın ve Apache ile MySQL modüllerini "Start" butonuna basarak başlatın.
2.  Web tarayıcınızda `http://localhost/phpmyadmin` adresine gidin.
3.  Üst menüden "Veritabanları" sekmesine tıklayın, veritabanı adı olarak `qr_login` yazın ve Karşılaştırma (Collation) için `utf8mb4_general_ci` seçerek "Oluştur" butonuna basın.
4.  Oluşturduğunuz `qr_login` veritabanını sol menüden seçin.
5.  Üst menüden "İçe Aktar" (Import) sekmesine tıklayın.
6.  "Dosya Seç" butonuna basarak proje klasörümüzdeki (`C:\xampp\htdocs\qrkod`) `database.sql` dosyasını seçin ve sayfanın altındaki "İçe Aktar" butonuna tıklayın. Bu işlem, gerekli tüm tabloları oluşturacaktır.
7.  İçe aktarma bittikten sonra, aynı veritabanı seçiliyken "SQL" sekmesine tıklayın ve test kullanıcısını oluşturmak için aşağıdaki komutu yapıştırıp çalıştırın:
    *Açıklama: Sistem, QR kod ile giriş onayı geldiğinde, bu onayın geçerli bir kullanıcıya ait olup olmadığını kontrol eder. Bu kısıtlama nedeniyle, test yapabilmek için veritabanında en az bir kullanıcı olması gerekir.*
    ```sql
    INSERT INTO `users` (`id`, `uuid`, `email`, `password_hash`) VALUES (123, 'test-uuid-123', 'test@example.com', 'some-hash');
    ```

**Adım 2.3: PHP Eklentilerini Aktifleştirme (KRİTİK ADIM!)**

*Açıklama: PHP, farklı ortamlarda (web sunucusu ve komut satırı) farklı yapılandırma dosyaları (`php.ini`) kullanabilir. Bizim sunucularımız komut satırından çalıştığı için, komut satırının kullandığı `php.ini` dosyasını düzenlemeliyiz.*

1.  Bir komut istemi (CMD) açın ve `php --ini` komutunu çalıştırın.
2.  Çıktıda "Loaded Configuration File:" satırında yazan dosya yolunu bulun ve bu `php.ini` dosyasını bir metin düzenleyici (Notepad vb.) ile açın.
3.  Dosya içinde aşağıdaki iki satırı bulun ve başlarındaki noktalı virgülü (`;`) silerek onları aktif hale getirin:
    *   `extension=pdo_mysql`  (PHP'nin MySQL veritabanıyla konuşmasını sağlar)
    *   `extension=gd`           (PHP'nin QR kodu gibi resimleri anlık olarak oluşturmasını sağlar)

    **Önce:**
    `;extension=pdo_mysql`
    `;extension=gd`

    **Sonra:**
    `extension=pdo_mysql`
    `extension=gd`

4.  Dosyayı kaydedip kapatın.

---

**BÖLÜM 3: SUNUCULARI BAŞLATMA**

**Adım 3.1: Redis Sunucusu**

1.  İndirdiğiniz Redis `.zip` dosyasını `C:\redis` gibi bir klasöre çıkarın.
2.  Bu klasörün içindeki `redis-server.exe` dosyasına çift tıklayarak çalıştırın.
3.  Ekrana gelen siyah komut istemi penceresi, Redis sunucusunun kendisidir. **Uygulama çalıştığı sürece bu pencere açık kalmalıdır!**

**Adım 3.2: Proje Sunucuları**

Projemizin iki ayrı sunucusu vardır ve ikisinin de aynı anda çalışması gerekir. Bu yüzden **iki ayrı** komut istemi penceresi kullanacağız.

1.  **Terminal 1 - WebSocket Sunucusu (Gerçek Zamanlı Bildirimler İçin):**
    *   Proje klasöründe (`C:\xampp\htdocs\qrkod`) bir komut istemi açın.
    *   Şu komutu çalıştırın:
        ```
        php bin/server.php
        ```
    *   Bu pencerede "Server running..." gibi bir mesaj görmelisiniz. Bu pencere de açık kalmalıdır.

2.  **Terminal 2 - PHP Web Sunucusu (Web Sayfaları ve API İçin):**
    *   Proje klasöründe **ikinci bir** komut istemi açın.
    *   Şu komutu çalıştırın:
        ```
        php -S localhost:8000 -t . router.php
        ```
    *   Bu pencere de istekleri karşılamak için açık kalmalıdır.

---

**BÖLÜM 4: UYGULAMAYI TEST ETME**

Artık tüm sunucular çalıştığına göre, sistemi test edebiliriz.

1.  Web tarayıcınızda `http://localhost:8000/login.html` adresini açın. Ekranda bir QR kodu belirecektir.
2.  **Yeni bir tarayıcı sekmesinde** `http://localhost:8000/scanner.html` adresini açın. Bu sayfa, mobil uygulamanızı taklit eder.
3.  `login.html` sekmesine geri dönün ve klavyenizde **F12** tuşuna basarak Geliştirici Araçları'nı açın.
4.  Geliştirici Araçları'nda **"Network" (Ağ)** sekmesine tıklayın.
5.  Listede `session/new` ile başlayan bir istek göreceksiniz. Bu isteğe tıklayın ve sağda açılan panelde **"Preview" (Önizleme)** veya **"Response" (Yanıt)** sekmesine geçin.
6.  Burada gördüğünüz `sessionId` değerini (tırnak işaretleri olmadan) kopyalayın.
7.  `scanner.html` sekmesine geçin. Kopyaladığınız `sessionId`'yi ilgili alana yapıştırın. "User ID" alanının `123` olduğundan emin olun.
8.  **"Confirm Scan"** butonuna tıklayın.

**SONUÇ:**
*   `scanner.html` sayfasında yeşil renkte "Success: Login confirmed." mesajı belirecektir.
*   Hemen ardından `login.html` sekmesine baktığınızda, QR kodunun kaybolduğunu ve "Login successful! Redirecting..." yazdığını göreceksiniz.

Tebrikler! Projeyi başarıyla çalıştırdınız.
