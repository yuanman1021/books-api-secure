# Chapter 11 — Vue 3 Frontend (JWT auth)

A **Vue 3 + Vite + Pinia + Vue Router + Axios** single-page app that consumes the Chapter 11 JWT-secured Books API. Demonstrates the full authentication flow: register, login, token persistence, protected routes, and role-based UI.

## What's inside

```
frontend/
├── package.json           # Vue 3 + Vite + Pinia + Vue Router + Axios
├── vite.config.js
├── index.html
├── .env.development       # VITE_API_BASE_URL=http://localhost:8000
├── .gitignore
└── src/
    ├── main.js
    ├── style.css
    ├── App.vue                          # top nav + auth-aware links + logout
    ├── api/
    │   └── client.js                    # Axios + JWT interceptor + auto-logout on 401
    ├── stores/
    │   └── auth.js                      # Pinia store (token + user, persisted)
    ├── router/
    │   └── index.js                     # public + requiresAuth routes
    ├── views/
    │   ├── BookList.vue                 # browse (guest), create/edit (member), delete (admin)
    │   ├── Login.vue
    │   ├── Register.vue
    │   └── Me.vue                       # current user (requires JWT)
    └── components/
        └── BookForm.vue                 # create / edit form
```

## What you can do

| As a…              | You can…                                     |
|--------------------|----------------------------------------------|
| Guest              | Browse and search books                       |
| Authenticated user | Browse, create, edit (any book)               |
| Admin              | All of the above **plus** delete books        |

The UI hides buttons you can't use, but the backend re-checks every action — so even if you peek at the HTML, you can't bypass the JWT auth or the admin-only delete.

## Prerequisites

| Tool                                                | Verify with |
|-----------------------------------------------------|-------------|
| Node.js 18 +                                        | `node -v`   |
| npm 9 +                                             | `npm -v`    |
| Laragon with MySQL running                          | Laragon's MySQL icon green |
| `books_api` database with `users` + `books`         | Step 1 below |
| Ch11 backend running at `http://localhost:8000`     | Step 2 below |

## Setup

### 1. Set up MySQL (one-time)

```
cd ..                  # back to Ch11_BooksAPI_Solution
mysql -u root < sql/schema.sql
```

The schema creates `books_api`, the `books` table, and the `users` table with two seeded users:

| Email              | Password | Role   |
|--------------------|----------|--------|
| admin@books.test   | password | admin  |
| member@books.test  | password | member |

### 2. Start the Chapter 11 backend

Still in `Ch11_BooksAPI_Solution`:

```
composer install

# Copy the env template and set a real JWT_SECRET:
copy .env.example .env
# Then edit .env and set:
#   JWT_SECRET=<run: php -r "echo bin2hex(random_bytes(32));">

php -S localhost:8000 -t public
```

Smoke-test:

```
curl http://localhost:8000/
```

### 3. Install the frontend dependencies

In a **new** terminal:

```
cd Ch11_BooksAPI_Solution/frontend
npm install
```

### 4. Start the dev server

```
npm run dev
```

Open the URL Vite prints (default: `http://localhost:5173/`).

## Walk-through

1. **Visit `/` as a guest** — you see the books list and a yellow note saying you need to log in to write.
2. **Click "Login"** — the email and password fields are pre-filled with the member account for convenience.
3. **Sign in as `member@books.test` / `password`** — the header now shows your name and a blue **member** tag. A **+ New book** button appears.
4. **Add a new book** — the form posts to `/api/books` with the JWT in the Authorization header. The list refreshes.
5. **Try the Delete button** — you don't see one as a member. Even if you fake one, the server returns 403.
6. **Logout, then login as `admin@books.test` / `password`** — the tag turns yellow and **Delete** buttons appear next to every book.
7. **Visit `/me`** — calls `GET /auth/me` with the token; shows your full profile.
8. **Refresh the browser** — the token and user persist in `localStorage`, so you stay logged in.
9. **Try `/me` after logging out** — the router guard sends you to `/login?redirect=/me`.

## How the auth flow works

```
                      ┌──────────────┐
                      │  Vue 3 SPA   │
   (1) login form ───►│              │
                      │              │
                      │  Pinia       │
                      │  auth store  │
                      └──────┬───────┘
                             │
                  (2) POST /auth/login
                             │
                             ▼
                      ┌──────────────┐
                      │  Slim + JWT  │
                      │     API      │
                      └──────┬───────┘
                             │
                  (3) { access_token, user }
                             │
                             ▼
                      ┌──────────────┐
                      │  localStorage│  ←─ persist
                      └──────┬───────┘
                             │
              (4) for every API call:
                  Authorization: Bearer <token>
                             │
                             ▼
                      ┌──────────────┐
                      │  Slim Auth   │
                      │  Middleware  │  verifies signature + exp
                      └──────────────┘
```

Key files:
- **`src/stores/auth.js`** — `login()`, `register()`, `logout()`; persists token + user in `localStorage`.
- **`src/api/client.js`** — Axios with two interceptors: attach the JWT, auto-logout on 401.
- **`src/router/index.js`** — `meta.requiresAuth` + `beforeEach` guard redirect to `/login` with a `?redirect=` query.

## Negative test ideas

- Tamper with `localStorage.token` (open DevTools → Application → Local Storage). The next API call returns 401, the response interceptor logs you out, and the router sends you back to `/login`.
- Try to delete a book as a member (use a curl or modify the page) — backend returns 403.
- Wait for the token to expire (`JWT_TTL=10` in `.env` for a quick test) — the next call returns 401 and you're logged out.

## Build for production

```
npm run build       # outputs ./dist
npm run preview     # serves dist/ on http://localhost:4173
```

Before deploying, set `VITE_API_BASE_URL` in `.env.production` to the deployed API URL and rebuild.

## Troubleshooting

| Symptom                                            | Likely cause                                                 | Fix |
|----------------------------------------------------|--------------------------------------------------------------|-----|
| 401 immediately after login                        | Backend can't decode the token (wrong secret)                | Make sure `JWT_SECRET` in backend `.env` is set (not the placeholder), then restart PHP. |
| CORS error from `/auth/login`                      | Backend's CORS middleware not including the frontend origin  | The Ch11 backend allows `*` by default — restart it. |
| Token gone after page refresh                      | Browser blocks `localStorage` (private window / cookie wall) | Use a normal window or disable the block. |
| Login form returns "Network error"                 | Backend not running                                          | Start `php -S localhost:8000 -t public`. |
| Member can see a Delete button                     | They shouldn't                                                | Check `v-if="auth.isAdmin"` and refresh. |
| Backend says `JWT_SECRET is missing or still set…` | `.env` still has the placeholder                              | Run `php -r "echo bin2hex(random_bytes(32));"` and put the value into `.env`. |
