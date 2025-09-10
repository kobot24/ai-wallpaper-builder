[README.md](https://github.com/user-attachments/files/22263503/README.md)
# AI Wallpaper Builder

Ein WordPress-Plugin für WooCommerce, das KI-generierte personalisierte Tapeten ermöglicht.

## 📋 Übersicht

Das AI Wallpaper Builder Plugin ermöglicht es Kunden, personalisierte Tapeten mit KI-Unterstützung zu erstellen und zu bestellen. Es integriert sich nahtlos in WooCommerce und bietet eine intuitive Benutzeroberfläche für die Anpassung von Größe, Material und Design.

## ✨ Features

- **KI-Bildgenerierung** mit OpenAI Integration
- **Interaktive Größenanpassung** mit visueller Vorschau
- **Materialauswahl** (Vlies, Vinyl, etc.)
- **Bahnenbreite-Konfiguration** (53cm, 70cm)
- **Crop-Funktionalität** für präzise Bildanpassung
- **WooCommerce Integration** mit Preisberechnung pro m²
- **Responsive Design** für alle Geräte
- **Mehrsprachige Unterstützung** (Deutsch/Englisch)

## 🚀 Installation

1. **Plugin herunterladen**
   ```bash
   git clone https://github.com/kobot24/ai-wallpaper-builder.git
   ```

2. **Plugin aktivieren**
   - Kopieren Sie den Ordner in `/wp-content/plugins/`
   - Aktivieren Sie das Plugin im WordPress Admin-Bereich

3. **WooCommerce konfigurieren**
   - Stellen Sie sicher, dass WooCommerce installiert und aktiviert ist
   - Konfigurieren Sie Ihre Produktvariationen (Material, Bahnenbreite)

4. **OpenAI API einrichten** (optional)
   - Gehen Sie zu `WooCommerce > Produkte > AI Wallpaper`
   - Fügen Sie Ihren OpenAI API-Schlüssel hinzu

## ⚙️ Konfiguration

### Produkt-Setup

1. **Produkt erstellen**
   - Erstellen Sie ein neues WooCommerce-Produkt
   - Aktivieren Sie "AI Wallpaper Builder" im Produkt-Tab

2. **Variationen konfigurieren**
   - Material: Vlies, Vinyl fein, Vinyl Textil, etc.
   - Bahnenbreite: 53cm, 70cm
   - Größe: Breite × Höhe (cm)

3. **Preise einstellen**
   - Grundpreis pro m²
   - MwSt.-Einstellungen
   - Versandkosten

### Admin-Einstellungen

- **OpenAI API-Schlüssel** für KI-Bildgenerierung
- **Standard-Bilder** für verschiedene Materialien
- **Preisberechnung** (pro m² oder feste Preise)
- **Vorschau-Button** aktivieren/deaktivieren

## 🎨 Verwendung

### Für Kunden

1. **Produkt auswählen** mit aktiviertem AI Wallpaper Builder
2. **Größe eingeben** (Breite × Höhe in cm)
3. **Material wählen** aus den verfügbaren Optionen
4. **Bahnenbreite** auswählen (53cm oder 70cm)
5. **Vorschau anzeigen** für visuelle Kontrolle
6. **Bild anpassen** mit Crop-Funktionalität
7. **In den Warenkorb** legen und bestellen

### Für Administratoren

- **Produktverwaltung** über WooCommerce-Produktseiten
- **Bestellverwaltung** mit allen AWB-Daten
- **Preisüberwachung** und Anpassungen
- **Kundenkommunikation** über Korrekturfunktionen

## 🔧 Technische Details

### Systemanforderungen

- **WordPress:** 5.0+
- **WooCommerce:** 5.0+
- **PHP:** 7.4+
- **MySQL:** 5.6+

### Dateistruktur

```
ai-wallpaper-builder/
├── ai-wallpaper-builder.php          # Haupt-Plugin-Datei
├── includes/                         # PHP-Klassen
│   ├── class-awb-admin.php          # Admin-Funktionalität
│   ├── class-awb-frontend.php       # Frontend-Darstellung
│   ├── class-awb-woo.php            # WooCommerce-Integration
│   ├── class-awb-openai.php         # OpenAI-API
│   └── ...
├── assets/                          # CSS, JS, Bilder
│   ├── css/                        # Stylesheets
│   ├── js/                         # JavaScript-Dateien
│   ├── img/                        # Icons und Bilder
│   └── fonts/                      # Schriftarten
└── README.md                       # Diese Datei
```

### Hooks und Filter

- `woocommerce_single_product_summary` - AWB-Box anzeigen
- `woocommerce_add_cart_item_data` - Cart-Daten verarbeiten
- `woocommerce_get_item_data` - Cart-Anzeige anpassen
- `woocommerce_cart_item_thumbnail` - Thumbnail-Anzeige

## 🐛 Fehlerbehebung

### Häufige Probleme

1. **AWB-Box wird nicht angezeigt**
   - Prüfen Sie, ob das Plugin aktiviert ist
   - Überprüfen Sie die WooCommerce-Konfiguration
   - Kontrollieren Sie die Produkt-Einstellungen

2. **Preise werden nicht berechnet**
   - Stellen Sie sicher, dass Größenfelder ausgefüllt sind
   - Prüfen Sie die m²-Preis-Einstellungen
   - Überprüfen Sie die WooCommerce-Preislogik

3. **KI-Bildgenerierung funktioniert nicht**
   - Überprüfen Sie den OpenAI API-Schlüssel
   - Kontrollieren Sie die Internetverbindung
   - Prüfen Sie die API-Limits

### Debug-Modus

Aktivieren Sie den WordPress Debug-Modus für detaillierte Fehlermeldungen:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📝 Changelog

### Version 5.9.124-stable.2.2
- ✅ Bricks Builder Kompatibilität
- ✅ CSS-Spezifität für Theme-Konflikte erhöht
- ✅ WooCommerce-Tabs wiederhergestellt
- ✅ Icons-Anzeige verbessert
- ✅ Doppelte AWB-Box entfernt

### Version 5.9.124-stable.2.0
- ✅ Bricks Builder CSS-Konflikte behoben
- ✅ Höchste CSS-Spezifität implementiert
- ✅ Inline CSS für bessere Kompatibilität

### Version 5.9.124-stable.1.9
- ✅ Doppelten Hook entfernt
- ✅ Icons CSS verbessert
- ✅ WooCommerce-Tabs geschützt

## 🤝 Beitragen

Wir freuen uns über Beiträge! Bitte:

1. Forken Sie das Repository
2. Erstellen Sie einen Feature-Branch
3. Committen Sie Ihre Änderungen
4. Erstellen Sie einen Pull Request

### Entwicklung

```bash
# Repository klonen
git clone https://github.com/kobot24/ai-wallpaper-builder.git

# In den Ordner wechseln
cd ai-wallpaper-builder

# Abhängigkeiten installieren (falls vorhanden)
composer install
npm install
```

## 📄 Lizenz

Dieses Plugin steht unter der GPL-2.0-Lizenz. Siehe [LICENSE](LICENSE) für Details.

## 🆘 Support

- **GitHub Issues:** [Probleme melden](https://github.com/kobot24/ai-wallpaper-builder/issues)
- **Dokumentation:** [Wiki](https://github.com/kobot24/ai-wallpaper-builder/wiki)
- **E-Mail:** kobot24@users.noreply.github.com

## 🙏 Danksagungen

- **WooCommerce** für die E-Commerce-Plattform
- **OpenAI** für die KI-Bildgenerierung
- **WordPress Community** für Unterstützung und Feedback

---

**Entwickelt mit ❤️ für die WordPress-Community**
