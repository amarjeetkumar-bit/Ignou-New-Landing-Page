# IGNOU Landing Page — Vercel (plain PHP)

This is the IGNOU lead-capture landing page converted from Laravel to **plain PHP**
so it runs on **Vercel** using the community [`vercel-php`](https://github.com/vercel-community/php)
runtime. There is **no framework and no database** — the form posts leads directly
to the LeadSquared API, exactly like the original app.

## Structure

```
.
├── api/
│   └── index.php        # router + form handler (replaces Laravel routes + controller)
├── templates/
│   ├── index.php        # landing page
│   └── thankyou.php     # thank-you page
├── assets/              # images + CSS (served as static files)
├── favicon.png
├── robots.txt
├── vercel.json          # runtime + routing config
└── .env.example         # required environment variables
```

## Routes

| Method | Path                   | Action                                            |
|--------|------------------------|---------------------------------------------------|
| GET    | `/`                    | Landing page                                      |
| POST   | `/students-lead-save`  | Validates and sends the lead to LeadSquared       |
| GET    | `/thank-you`           | Thank-you page                                    |

## Deploy

1. Push this folder to a GitHub repository.
2. In Vercel, **Add New → Project** and import the repo.
   Set **Framework Preset = Other**. Leave build/output settings empty.
3. Add the environment variables (**Settings → Environment Variables**):
   - `LSQ_ACCESS_KEY`
   - `LSQ_SECRET_KEY`
   - `LSQ_HOST` *(optional — defaults to `api-in21.leadsquared.com`)*
4. Deploy.

Or from the CLI:

```bash
npm i -g vercel
vercel        # preview
vercel --prod # production
```

## Notes

- The PHP runtime `vercel-php@0.9.0` bundles PHP 8.5 with the `curl` extension,
  which the lead handler needs.
- Laravel's CSRF token was removed because there is no Laravel session layer here.
  If you want spam protection, add a captcha (e.g. Google reCAPTCHA / hCaptcha)
  or a honeypot field.
- On a validation or API error the user is redirected back to `/?error=...` and a
  red banner is shown at the top of the page.
- **Security:** the original archive contained a real `.env` with live keys. Treat
  those as compromised and rotate them. Only put credentials in Vercel's
  Environment Variables — never commit them.
