# Allan's Phone Store

A full-stack phone shop built with **HTML5, CSS3, JavaScript, PHP 8 (PDO) and MySQL**, for XAMPP.

Features: registration and login (hashed passwords, sessions), 24 phones in 4 categories, search / filter / sort,
cart with stock rules, likes, 1-5 star ratings with reviews, light and dark theme, profile dropdown,
simulated checkout inside a database transaction, and order history.

## 1. Run it on XAMPP (about 3 minutes)

1. Install and open **XAMPP**. Start **Apache** and **MySQL**.
2. Unzip this folder to `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\allans-phone-store\`
   (on macOS/Linux: `/Applications/XAMPP/htdocs/` or `/opt/lampp/htdocs/`).
3. Open **http://localhost/phpmyadmin**, click the **SQL** tab, paste the whole of `database/schema.sql`, press **Go**.
   This creates the database `allans_phone_store`, all tables, 24 phones and 3 demo users.
4. Open **http://localhost/allans-phone-store/**

Default XAMPP database login is `root` with an empty password, which is what `config/config.php` expects.
If yours is different, edit `config/config.php`.

**Demo accounts** (password for all: `Demo@1234`): `demo@allansphones.test`, `amaka@allansphones.test`, `tunde@allansphones.test`

## 2. Deploy it before the stand-up

The app is plain PHP + MySQL, so any PHP host works. I can't deploy from inside this chat, so here are two routes you can do yourself.

### Option A: InfinityFree (free, simplest, no card)
1. Sign up at infinityfree.com and create a hosting account (you get a free subdomain).
2. In the control panel open **MySQL Databases**, create a database, and note the **host, database name, username and password**.
3. Open **phpMyAdmin** from the panel, select your new database, go to **SQL**, and paste `database/schema.sql`.
   **Delete the first two statements** (`CREATE DATABASE ...` and `USE ...`) because the database already exists.
4. Edit `config/config.php` and put the host, name, user and password from step 2 in the fallback values.
5. Upload everything inside `allans-phone-store/` into the `htdocs` folder (File Manager or FTP).
6. Visit your subdomain. Done. Test: register, add to cart, check out, switch theme.

### Option B: Docker host (Render, Railway, Fly.io)
1. Push this folder to a GitHub repo. The included `Dockerfile` builds a PHP 8.2 + Apache image.
2. Create a MySQL database on the host (Railway offers one) and import `database/schema.sql` into it
   (remove the first two statements if the host gives you a fixed database name).
3. Create a web service from the repo and set environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. Deploy and open the URL.

Deployment checklist: HTTPS works, you can register and sign in, the cart survives sign-out/sign-in, an order reduces stock.

## 3. Folder map

| Path | What it does |
|---|---|
| `index.php` | Catalogue: search, category filter, sort, product cards |
| `product.php` | Product details, specs, rating form, reviews |
| `register.php`, `login.php`, `logout.php` | Accounts and sessions |
| `profile.php` | Profile, change name/password, liked phones |
| `cart.php`, `checkout.php`, `orders.php`, `order.php` | Cart, simulated checkout, order history and confirmation |
| `api/like.php`, `api/cart.php` | JSON endpoints used by JavaScript |
| `includes/` | Shared bootstrap (session, CSRF, helpers), header, footer, product card |
| `config/` | Settings and the PDO connection |
| `database/schema.sql` | Tables, constraints and sample data |
| `assets/` | CSS, JS and generated SVG phone images |
| `docs/` | Stand-up notes and test checklist |

## 4. Security notes
- Passwords use `password_hash()` / `password_verify()`.
- All queries use prepared statements. All output is escaped with `htmlspecialchars`.
- Every state-changing request needs a CSRF token. Sign-out is a POST.
- `session_regenerate_id()` runs on login. Cookies are HttpOnly and SameSite=Lax.
- User ids always come from the session, never from a URL or form, so one user cannot read another user's cart, profile or orders.
- Prices are always read from MySQL. The browser only sends a product id and a quantity.
