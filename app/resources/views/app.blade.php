<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AEGIS Mailing</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "AEGIS Network",
        "url": "https://aegisnetwork.fr/",
        "logo": "https://aegisnetwork.fr/signatures/aegis-logo-compact-512.png",
        "email": "contact@aegisnetwork.fr",
        "telephone": "+33482532699",
        "description": "Conseil, accompagnement, cadrage, coordination et optimisation IT, réseau et télécom pour TPE/PME.",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "19 Avenue de Pérouges",
            "postalCode": "69580",
            "addressLocality": "Sathonay-Camp",
            "addressCountry": "FR"
        },
        "areaServed": [
            { "@type": "City", "name": "Lyon" },
            { "@type": "AdministrativeArea", "name": "Rhône" },
            { "@type": "AdministrativeArea", "name": "Isère" },
            { "@type": "AdministrativeArea", "name": "Ain" }
        ]
    }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full antialiased text-gray-900">
    @inertia
</body>
</html>
