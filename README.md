# WHMCS Menu Manager

A powerful and flexible menu management module for WHMCS that allows administrators to fully customize navigation menus with modern styling and Font Awesome icons.

**Author:** [Shahid Malla](https://shahidmalla.com)  
**Version:** 4.0  
**Website:** [https://shahidmalla.com](https://shahidmalla.com)

![Menu Manager Preview](https://shahidmalla.com/assets/images/menumanager-preview.jpg)

## 🌟 Features

- Create separate menus for logged-in clients and guests
- Easily add, edit, and rearrange menu items through admin interface
- Support for Font Awesome icons (version 6)
- Create multi-level dropdown menus
- Mobile-responsive design
- Works with all standard WHMCS templates
- No core file modifications required

## 📥 Installation

1. Download the latest release from [GitHub](https://github.com/shahidmalla/whmcs-menu-manager) or from [Shahid Malla's website](https://shahidmalla.com/products/whmcs-menu-manager)

2. Upload the `menumanager` folder to your WHMCS installation's `/modules/addons/` directory

3. Login to your WHMCS admin area and navigate to **Setup → Addon Modules**

4. Find "Menu Manager" in the list and click **Activate**

5. Grant administrator roles access to the module as needed

## 🔧 Configuration

1. Go to **Addons → Menu Manager** in your WHMCS admin area

2. Use the tabs to switch between Client Menu (for logged-in users) and Guest Menu (for visitors)

3. Add, edit, or remove menu items as needed:
   - **Menu Name**: The text displayed in the menu
   - **URL**: The link destination (e.g., `index.php` or `clientarea.php?action=services`)
   - **Icon**: Font Awesome class (e.g., `fas fa-home`)
   - **Order**: Controls the position of the menu item
   - **Parent Menu**: Set a parent to create dropdown menus
   - **Status**: Toggle menu items without deleting them

4. After making changes, click the **Clear All Caches** button to apply changes to your site

## 🚀 Common URLs

| Page | URL |
|------|-----|
| Home | `index.php` |
| Client Area | `clientarea.php` |
| Services | `clientarea.php?action=services` |
| Domains | `clientarea.php?action=domains` |
| Invoices | `clientarea.php?action=invoices` |
| Support Tickets | `clientarea.php?action=tickets` |
| Announcements | `index.php?rp=/announcements` |
| Knowledgebase | `index.php?rp=/knowledgebase` |
| Network Status | `index.php?rp=/serverstatus` |
| Contact Us | `index.php?rp=/contact` |

## ⚠️ Troubleshooting

If menu changes don't appear on your site:

1. Click the **Clear All Caches** button in the Menu Manager module
2. Try the **Reset Module Files** button to recreate all module files
3. Temporarily disable template caching in WHMCS: **System Settings → General Settings → System Tab → Template Cache → Never Cache**
4. Check your browser's cache or try in an incognito/private window
5. Ensure your template is compatible (most standard WHMCS templates are supported)

## 🔄 Version History

- **4.0**: Complete rewrite with improved mobile responsiveness and better template compatibility
- **3.0**: Added support for multi-level dropdown menus
- **2.0**: Added Font Awesome integration
- **1.0**: Initial release

## 🔗 Support & Updates

For support, feature requests, or to get the latest updates:

- Visit [Shahid Malla's official website](https://shahidmalla.com)
- Follow Shahid on [Twitter](https://twitter.com/shahidmalla_)
- Connect on [LinkedIn](https://linkedin.com/in/shahidmalla)

## 📝 About the Developer

This module is developed and maintained by [Shahid Malla](https://shahidmalla.com), a web developer specializing in WHMCS integrations and custom modules. With over a decade of experience in web development, Shahid creates tools that help hosting providers and web professionals enhance their WHMCS installations.

Visit [shahidmalla.com](https://shahidmalla.com) to discover more premium WHMCS modules and services.

## 📜 License

This WHMCS Menu Manager is released under the [MIT License](LICENSE).

---

Made with ❤️ by [Shahid Malla](https://shahidmalla.com)
