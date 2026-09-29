---
paths:
  - 'app/Support/Dashboard/Reports/**'
---

# Reports

## DOMPDF holds the whole document in memory
Measured ~0.15 MB and ~20 ms per 8-column table row; one big table exhausted a 128 MB queue worker in Cellmap.php. PdfWriter renders one page-sized <table> per page, caps rows at dashboard.reports.pdf_max_rows (1000) and raises memory_limit (pdf_memory_limit, 512M) while rendering. Don't raise the cap without raising the memory limit, or switch LARAVEL_PDF_DRIVER to a Chromium driver (gotenberg/cloudflare) for big PDFs. PHP 8.4 won't lower memory_limit below reserved memory — never force-restore it.
