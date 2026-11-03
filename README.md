# Medicine Shop

An inventory and billing system for a medicine shop, built with plain PHP and MySQL. It tracks suppliers, customers, products and stock, creates purchase and sales bills, records supplier payments, and prints invoices and inventory reports.

## Features

- **Dashboard** with sales vs. purchases over the last 3 or 6 months, stock health, supplier balances and recent transactions
- **Suppliers (companies)** with contact details, purchase history and outstanding balances
- **Customers** with a per-customer sales ledger
- **Products** with composition, purchase rate, sale rate and current stock
- **Purchase bills** that add stock and support partial payments, plus a payment log and opening balances for existing suppliers
- **Sales bills** that deduct stock, with a live product search
- **Print-ready documents**: A4 sales and purchase invoices and a landscape inventory report (print or save as PDF from the browser)
- **Page finder** (Ctrl/Cmd + K) to jump to any page
- **Optional AI assistant** (OpenAI) that answers questions about the app, looks up stock and prices, and helps fill forms. It never submits bills or deletes data.

## Tech stack

- PHP 8 with MySQLi, no framework
- MySQL / MariaDB
- Bootstrap 5, Font Awesome, jQuery (via CDN)
- ApexCharts and Lucide icons (bundled in `js/vendor/`)
- Dashboard motion: GSAP with ScrollTrigger, SplitText and ScrambleText, Lenis smooth scrolling, and a trimmed Three.js build for the 3D pill hero (all bundled in `js/vendor/` with their licenses). It turns off with the system's reduced-motion setting or the "Pause motion" control in the page finder (Ctrl/Cmd + K)

There is no build step and no package manager.

## Getting started

1. Install [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP + MySQL setup).
2. Clone this repository into your web root:

   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/MSaadShahid1/medicine-shop.git
   ```

3. Start Apache and MySQL from the XAMPP control panel.
4. Create a database named `medicine_shop` and import `medicine_shop.sql` (for example through phpMyAdmin at `http://localhost/phpmyadmin`). The file contains the table structure only, so the app starts empty.
5. If your MySQL login is not the XAMPP default (`root` with no password), update the credentials in `config.php`.
6. Open `http://localhost/medicine-shop/dashboard.php`.

### Optional: demo data

To fill the app with sample suppliers, customers, products and bills, run this from the project folder (it works from the command line only, not the browser):

```powershell
& 'C:\xampp\php\php.exe' tools/demo_data.php seed
```

See `DEMO_DATA.md` for how to verify or remove the demo records.

### Optional: AI assistant

Create a `.env` file in the project root:

```
OPENAI_API_KEY=your-key-here
```

The key stays on the server and is never sent to the browser. `.htaccess` blocks direct access to `.env`, and the assistant only answers requests from the local machine.

## Project structure

| Path | Purpose |
| --- | --- |
| `config.php` | Database connection and shared helpers |
| `sidebar.php` | Shared top navigation included on every page |
| `dashboard.php` | Main dashboard |
| `add_*.php`, `edit_*.php`, `view_*.php` | Manage companies, customers and products |
| `create_purchase_bill.php`, `create_sales_bill.php` | Create bills and update stock |
| `*_pdf.php` | Printable invoices and inventory report |
| `ai_agent.php` | Server endpoint for the AI assistant |
| `css/`, `js/` | Styles and scripts |
| `medicine_shop.sql` | Database schema |

## Security notes

This project is intended for use on a local machine or a trusted network.

- There is no login system.
- `reset_system.php` wipes all data and is protected only by the hardcoded password `admin123`. Change or remove it before putting the app anywhere others can reach.
