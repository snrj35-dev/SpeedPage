
# SpeedPage

Personal localhost homepage / control panel experiment.  
This is an amateur project; you are free to develop and modify it as you like.  
⚠️ However, if something breaks, the responsibility is entirely yours. :D  

---

##  Libraries Used
- [Bootstrap](https://getbootstrap.com/)  
- [Font Awesome](https://fontawesome.com/)  

---

##  Installation
1. Upload the files to **localhost** or your own domain.  
2. Edit the `settings.php` file according to your setup:  

// If running in the root directory:
define('BASE_PATH', '/');

// If running in a subfolder:
define('BASE_PATH', '/newsite/');

// For localhost:
define('BASE_URL', 'http://localhost' . BASE_PATH);

// For your domain:
define('BASE_URL', 'http://yourdomain.com' . BASE_PATH);


3. Default **admin panel** login credentials:  
   - Username: `admin`  
   - Password: `admin`  

4. Open the project in your browser and start using it.  

---

##  Admin Panel Features
- Basic site settings  
- Add / delete pages  
- Add / delete menus  
- Add / delete modules  
- Add / delete users  
- Database management (backup / restore)  
- PWA support  
- AJAX-based page loading
  
---

##  Notes
- The project is designed with a modular structure.  
- PWA support is included (`manifest.json` + `service-worker.js`).  
- Open to development; feel free to customize it for your needs.

---

##  Module Example
In the `mods` folder, there is an example **library module** (`kitaplik.zip`).  
Unzip it and use it as a reference to develop your own modules.  

---

### README.md (Türkçe versiyon)


Kişisel localhost ana sayfa / kontrol paneli denemesi.  
Amatör bir projedir; istediğiniz gibi geliştirme ve değiştirme iznine sahipsiniz.  
⚠️ Ancak herhangi bir yerde patlarsa, sorumluluk tamamen size aittir. :D  

---

##  Kullanılan Kütüphaneler
- [Bootstrap](https://getbootstrap.com/)  
- [Font Awesome](https://fontawesome.com/)  

---

##  Kurulum
1. Dosyaları **localhost** veya kendi alan adınıza yükleyin.  
2. `settings.php` dosyasını kendi kurulumunuza göre düzenleyin:  

// Kök dizinde çalışacaksanız:
define('BASE_PATH', '/');

// Örneğin alt klasörde çalışacaksanız:
define('BASE_PATH', '/yenisite/');

// Localhost için:
define('BASE_URL', 'http://localhost' . BASE_PATH);

// Alan adınız için:
define('BASE_URL', 'http://alanadiniz.com' . BASE_PATH);

3. Varsayılan **admin paneli** giriş bilgileri:  
   - Kullanıcı adı: `admin`  
   - Şifre: `admin`  

4. Tarayıcıdan projenizi açın ve kullanmaya başlayın.  

---

##  Admin Panel Özellikleri
- Temel site ayarları  
- Sayfa ekleme / silme  
- Menü ekleme / silme  
- Modül ekleme / silme  
- Kullanıcı ekleme / silme  
- Veritabanı yönetimi (yedek alma / yükleme)  
- PWA desteği  
- AJAX ile sayfa yükleme  

---

##  Notlar
- Proje modüler yapıda tasarlanmıştır.  
- PWA desteği vardır (`manifest.json` + `service-worker.js`).  
- Geliştirmeye açıktır; kendi ihtiyaçlarınıza göre özelleştirebilirsiniz.  


## Modül Örneği
`mods` klasöründe örnek bir **kitaplık modülü** (`kitaplik.zip`) bulunmaktadır.  
Zip dosyasını açarak kendi modüllerinizi geliştirmek için referans olarak kullanabilirsiniz.
