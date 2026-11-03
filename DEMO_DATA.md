# Demo data for visual previews

Added on 24 October 2026, without replacing any existing record.

## What was added

| Record type | Demo records |
| --- | ---: |
| Suppliers / companies | 6 |
| Customers | 12 |
| Products | 24 |
| Purchase bills | 39 (36 purchases + 3 opening balances) |
| Purchase line items | 144 |
| Sales invoices | 72 |
| Sales line items | 296 |
| Supplier payments | 50 |

Names begin with `[DEMO]`; bill references begin with `DEMO-`. People, companies, addresses, contact numbers and product brands are fictional. Phone numbers deliberately use the invalid `0000` prefix. This dataset is for interface previews, not trading, prescribing or financial reporting.

The transactions cover May–October 2026, with historical opening balances in April. The seed script uses the current six calendar months when installed again later. Examples include fully paid, part-paid and unpaid purchases, payment histories, opening balances, low stock, out-of-stock products and a 12-line sales invoice. Product stocks reconcile to purchase quantities minus sales quantities; paid amounts reconcile to payment history. Sales are fully paid, following the existing app model.

Existing data was fingerprinted before insertion and checked unchanged before committing. The entire insertion runs in one transaction. No screen, stylesheet or print template was changed to add demo data.

## Useful previews

- [Dashboard](http://localhost/Farms/dashboard.php)
- [Suppliers](http://localhost/Farms/view_companies.php) / [customers](http://localhost/Farms/view_customers.php) / [products](http://localhost/Farms/view_products.php)
- [Demo supplier ledger](http://localhost/Farms/company_details.php?id=5)
- [Demo customer ledger](http://localhost/Farms/customer_ledger.php?id=9)
- [12-line sales invoice](http://localhost/Farms/sales_bill_detail.php?id=86) / [print preview](http://localhost/Farms/sales_bill_pdf.php?id=86)
- [Part-paid purchase](http://localhost/Farms/purchase_bill_detail.php?id=57) / [payment form](http://localhost/Farms/add_payment.php?bill_id=57) / [print preview](http://localhost/Farms/purchase_bill_pdf.php?id=57)
- [Opening-balance editor](http://localhost/Farms/add_opening_balance.php?edit_id=58)
- [Demo supplier edit form](http://localhost/Farms/edit_company.php?id=5)
- [Demo customer edit form](http://localhost/Farms/edit_customer.php?id=9)
- [Inventory report](http://localhost/Farms/inventory_report.php) / [print preview](http://localhost/Farms/inventory_report_pdf.php)

These record IDs refer to this installation. The private manifest records the actual inserted IDs. New-entry forms remain empty by design; existing demo customers, suppliers and products are available in their selectors and browse lists.

**Important:** Saving, editing, paying or deleting demo records through the app is a real database change. Preview without saving if you want the automatic safe-removal check to remain valid. Demo totals are included in dashboard statistics and reports until removed.

## Backup and private ownership manifest

Full pre-demo MySQL backup (outside the web root):

`C:\xampp\Farms-data-backups\medicine_shop-before-demo-20261024-142518.sql`

Exact demo record IDs, fingerprints and original-row fingerprints:

`C:\xampp\Farms-data-backups\medicine_shop-demo-manifest.json`

Keep both files. The manifest is required for targeted removal. Do not delete it while demo records remain. Do not restore the full SQL backup just to remove demo data: a full restore also rolls back later genuine changes.

## Command-line management

Run in PowerShell from `C:\xampp\htdocs\Farms`:

```powershell
# Read-only integrity check, including the original records.
& 'C:\xampp\php\php.exe' tools/demo_data.php verify

# Non-destructive preview of whether automatic cleanup is safe.
& 'C:\xampp\php\php.exe' tools/demo_data.php remove --dry-run

# When you decide to remove the demo dataset (this deletes the owned demo rows).
& 'C:\xampp\php\php.exe' tools/demo_data.php remove --confirm-demo-only
```

The tool refuses automatic removal if demo records have been edited, are missing, or are referenced by new records outside the manifest. This prevents accidentally deleting work done after seeding. It never truncates tables, resets counters or deletes by a broad name prefix. Removal targets only the saved record IDs, in dependency order, within a transaction. If checks fail, stop and review the changed records instead of bypassing the checks.

Re-running `seed` while the manifest exists verifies the existing installation and does not insert duplicates. The tool is blocked over HTTP; it is not a web-accessible database reset endpoint.
