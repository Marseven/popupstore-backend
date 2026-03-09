<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} API</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --purple: #5B2D8E;
            --purple-light: #7B3FAE;
            --orange: #E8922D;
            --teal: #6BC5C4;
            --dark-950: #09090b;
            --dark-900: #18181b;
            --dark-800: #27272a;
            --dark-700: #3f3f46;
            --dark-500: #71717a;
            --dark-400: #a1a1aa;
            --dark-300: #d4d4d8;
            --dark-200: #e4e4e7;
            --dark-100: #f4f4f5;
            --dark-50: #fafafa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--dark-950);
            color: var(--dark-300);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Grain texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 100;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 4rem;
            padding-bottom: 3rem;
            border-bottom: 1px solid rgba(123, 63, 174, 0.15);
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1rem;
            background: rgba(123, 63, 174, 0.1);
            border: 1px solid rgba(123, 63, 174, 0.2);
            border-radius: 2px;
            margin-bottom: 2rem;
        }

        .logo-badge span {
            font-family: 'Syne', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--purple-light);
        }

        .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--teal);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--purple-light), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 1.05rem;
            color: var(--dark-500);
            line-height: 1.6;
            max-width: 550px;
            margin: 0 auto;
        }

        .version-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.5rem;
            padding: 0.3rem 0.75rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--dark-500);
            background: var(--dark-900);
            border: 1px solid var(--dark-800);
            border-radius: 2px;
        }

        .version-pill .v {
            color: var(--orange);
            font-weight: 500;
        }

        /* Section */
        .section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--purple-light);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, rgba(123, 63, 174, 0.2), transparent);
        }

        /* Endpoint cards */
        .endpoint-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .endpoint {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 1.25rem;
            background: var(--dark-900);
            border: 1px solid var(--dark-800);
            border-radius: 2px;
            transition: all 0.2s ease;
        }

        .endpoint:hover {
            border-color: rgba(123, 63, 174, 0.3);
            background: rgba(123, 63, 174, 0.03);
        }

        .method {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            padding: 0.2rem 0.5rem;
            border-radius: 2px;
            min-width: 3.2rem;
            text-align: center;
            flex-shrink: 0;
        }

        .method-get { background: rgba(107, 197, 196, 0.1); color: var(--teal); border: 1px solid rgba(107, 197, 196, 0.2); }
        .method-post { background: rgba(232, 146, 45, 0.1); color: var(--orange); border: 1px solid rgba(232, 146, 45, 0.2); }

        .endpoint-path {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: var(--dark-200);
            flex: 1;
        }

        .endpoint-desc {
            font-size: 0.8rem;
            color: var(--dark-500);
            text-align: right;
        }

        /* Info cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-card {
            padding: 1.5rem;
            background: var(--dark-900);
            border: 1px solid var(--dark-800);
            border-radius: 2px;
        }

        .info-card h3 {
            font-family: 'Syne', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark-100);
            margin-bottom: 0.5rem;
        }

        .info-card p {
            font-size: 0.85rem;
            color: var(--dark-500);
            line-height: 1.5;
        }

        .info-card .tag {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            background: rgba(123, 63, 174, 0.1);
            color: var(--purple-light);
            border-radius: 2px;
            margin-top: 0.75rem;
        }

        /* Footer */
        .footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--dark-800);
            text-align: center;
        }

        .footer p {
            font-size: 0.8rem;
            color: var(--dark-700);
        }

        .footer a {
            color: var(--purple-light);
            text-decoration: none;
        }

        .footer a:hover {
            color: var(--orange);
        }

        /* Auth badge */
        .auth-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.6rem;
            letter-spacing: 0.05em;
            padding: 0.15rem 0.4rem;
            border-radius: 2px;
            background: rgba(232, 146, 45, 0.1);
            color: var(--orange);
            border: 1px solid rgba(232, 146, 45, 0.15);
            flex-shrink: 0;
        }

        @media (max-width: 640px) {
            .endpoint-desc { display: none; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="logo-badge">
                <div class="dot"></div>
                <span>API en ligne</span>
            </div>
            <h1>Popup Store API</h1>
            <p class="subtitle">
                Plateforme e-commerce pour merch avec contenu multimédia exclusif.
                Chaque produit débloque une expérience unique via QR code.
            </p>
            <div class="version-pill">
                Laravel {{ app()->version() }} <span class="v">&middot; v1.0</span>
            </div>
        </header>

        <!-- About -->
        <div class="section">
            <div class="section-title">Le projet</div>
            <div class="info-grid">
                <div class="info-card">
                    <h3>Merch + Media</h3>
                    <p>Vente de produits physiques avec contenu audio/vidéo exclusif intégré, accessible via QR code.</p>
                    <span class="tag">QR Scan</span>
                </div>
                <div class="info-card">
                    <h3>Mobile Money</h3>
                    <p>Paiements via Airtel Money & Moov Money en francs CFA (XAF) pour le marché d'Afrique Centrale.</p>
                    <span class="tag">Ebilling API</span>
                </div>
                <div class="info-card">
                    <h3>API RESTful</h3>
                    <p>Authentification par token Bearer (Laravel Sanctum). JSON request/response sur tous les endpoints.</p>
                    <span class="tag">Sanctum</span>
                </div>
            </div>
        </div>

        <!-- Public endpoints -->
        <div class="section">
            <div class="section-title">Endpoints publics</div>

            <div class="endpoint-group">
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/products</span>
                    <span class="endpoint-desc">Liste des produits</span>
                </div>
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/products/featured</span>
                    <span class="endpoint-desc">Produits mis en avant</span>
                </div>
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/products/{slug}</span>
                    <span class="endpoint-desc">Détail d'un produit</span>
                </div>
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/categories</span>
                    <span class="endpoint-desc">Catégories de produits</span>
                </div>
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/collections</span>
                    <span class="endpoint-desc">Collections</span>
                </div>
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/collections/{slug}</span>
                    <span class="endpoint-desc">Détail d'une collection</span>
                </div>
            </div>

            <div class="endpoint-group">
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/cart</span>
                    <span class="endpoint-desc">Voir le panier</span>
                </div>
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/cart</span>
                    <span class="endpoint-desc">Ajouter au panier</span>
                </div>
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/auth/register</span>
                    <span class="endpoint-desc">Créer un compte</span>
                </div>
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/auth/login</span>
                    <span class="endpoint-desc">Connexion</span>
                </div>
            </div>
        </div>

        <!-- Authenticated endpoints -->
        <div class="section">
            <div class="section-title">Endpoints authentifiés</div>
            <div class="endpoint-group">
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/auth/me</span>
                    <span class="auth-badge">Bearer</span>
                    <span class="endpoint-desc">Profil utilisateur</span>
                </div>
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/orders</span>
                    <span class="auth-badge">Bearer</span>
                    <span class="endpoint-desc">Mes commandes</span>
                </div>
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/orders</span>
                    <span class="auth-badge">Bearer</span>
                    <span class="endpoint-desc">Passer commande</span>
                </div>
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/payments/initiate</span>
                    <span class="auth-badge">Bearer</span>
                    <span class="endpoint-desc">Initier un paiement</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; {{ date('Y') }} <a href="https://popupstore.ga">Popup Store</a> &middot; Tous droits réservés</p>
        </footer>
    </div>
</body>
</html>
