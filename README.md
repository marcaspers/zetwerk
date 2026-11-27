# Zetwerk

Nederlandstalig specialty-koffieplatform met een fictieve catalogus, community en demo-bestelketen. Gebouwd met Laravel 13, Blade, Livewire 3, onderdelen van de officiële Livewire-starterkit, Flux, Tailwind 4 en PostgreSQL 17. PHP 8.3–8.5; lokaal ontwikkeld met PHP 8.5. Afhankelijkheden staan vast in `composer.lock` en `package-lock.json`.

## Lokaal installeren

Vereist: PHP met `pdo_pgsql`, Composer, Node 22.12+ en Docker Compose (of een eigen PostgreSQL 17-server).

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
docker compose up -d
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000**. Het seedcommando `php artisan migrate --seed` maakt een complete demo met 3 roasters, 6 koffies, 12 varianten, 12 recepten, 18 reviews en 8 gebruikers. Run seeders één keer op een nieuwe database; voor een bewuste reset van uitsluitend deze demo-database: `php artisan migrate:fresh --seed`. Dat laatste verwijdert bestaande inhoud.

De database luistert alleen op `127.0.0.1:5442`. De gegevens in `.env.example` zijn uitsluitend lokale, fictieve ontwikkelgegevens. Gebruik bij hosting eigen databasecredentials en een nieuwe `APP_KEY`; commit nooit `.env`.

Wanneer de tijdens ontwikkeling gestarte container `zetwerk-postgres` al bestaat, hoef je `docker compose up` niet opnieuw uit te voeren. `docker start zetwerk-postgres` gebruikt dezelfde database en hetzelfde volume. De eerder handmatig aangemaakte container kan desgewenst worden vervangen door Compose; het volume `zetwerk_postgres` bewaart de gegevens.

Voor wijzigingen aan styling: `npm run dev` in een tweede terminal. Voor automatisch vrijgeven van verlopen reserveringen: `php artisan schedule:work`. Handmatig: `php artisan demo:expire`. Bij checkout, bestelstatus en beheer wordt eveneens op verlopen reserveringen gecontroleerd.

## Demo-accounts

| Rol | E-mail | Wachtwoord |
| --- | --- | --- |
| Liefhebber | noor@zetwerk.test | koffietijd |
| Beheer | beheer@zetwerk.test | koffietijd |

Registreren kan ook. Gebruik alleen testgegevens. Een nieuwe gebruiker krijgt nooit beheerrechten. Beheer is bereikbaar via `/beheer` en via de footer voor een ingelogde beheerder.

## Wat je kunt proberen

- Catalogus: zoeken op koffie, land of roaster; filteren op roaster, land, verwerking, brandprofiel, smaak en filter/espresso. Filters blijven in de URL staan.
- Koffiepagina: herkomst, varianten, smaak, brandbatch, verzender, verzendkosten, recepten en reviews. Zonder reviews verschijnt geen score.
- Recepten: publiceren, eigen recepten wijzigen/verwijderen, methodeafhankelijke invoer, berekende ratio, molen en bijbehorende maalstand, behulpzaam stemmen en opslaan.
- Reviews: één aanpasbare review per gebruiker en koffie, sterren en zetmethode. Geen echte aankoopverificatie op basis van demo-orders. Meldingen kunnen met reden worden afgehandeld door beheer.
- Mand: opgeslagen op de server, blijft behouden bij inloggen. Een tweede roaster vereist expliciete vervanging. Hoeveelheden en gewichten worden meegenomen naar checkout.
- Checkout: testadres, serverberekening, prijzen in centen en expliciete valuta. Betaling wordt op de bestelpagina gesimuleerd: geslaagd, mislukt, geannuleerd of in behandeling.
- Beheer: koffies en roasters aanmaken/bijwerken, variantprijzen en voorraad, nieuwe batches en brandbeleid, bestellingen doorzetten/accepteren/verzenden/leveren, mislukte overdrachten simuleren/herhalen, een werkelijk gebruikte batch toewijzen en communitycontent verbergen met reden.

De voorbeeldnamen, personen en prijzen zijn fictief. De verpakkingen en illustraties zijn zelfgemaakte CSS/SVG-elementen; er zijn geen externe roasterlogo's of foto's nodig. Hoogland heeft een recente batch, Heuvel een geblokkeerde oude batch, Ochtend wordt na bestelling gebrand en Decaf is uitverkocht en heeft geen reviews. Datums zijn relatief aan de seeddatum.

## Bestelgedrag en voorraad

`CartService` beheert de mand; `OrderService` beheert voorraad en statustransities. PostgreSQL-transacties en `FOR UPDATE`-locks op gebruiker, mand, varianten en batch beschermen tegen gelijktijdige bestellingen. Lockvolgorde van varianten is oplopend op ID. Een checkout heeft een unieke sleutel; herhaalde submits retourneren dezelfde bestelling. Nieuwe producten in de mand krijgen een nieuwe checkout-sleutel.

Een zak wordt bij orderplaatsing gereserveerd: variantvoorraad én bekende batchvoorraad worden verlaagd. Batchvoorraad wordt in deze demo uitgedrukt in zakken; beide gewichtsvarianten delen een lot, maar hebben eigen variantvoorraad. Voor een pilot kan batchvoorraad in grammen worden gemodelleerd als de roaster dat zo aanlevert. Reserveringen verlopen na 15 minuten. Mislukte/geannuleerde betalingen geven voorraad direct en exact eenmaal vrij. Een te late betaling maakt een verlopen order niet alsnog betaald; er is dan een nieuwe checkout nodig. Een succesvolle betaling maakt de reservering definitief.

De platformlimiet van 21 dagen is **fictief demo-beleid**, geen vastgesteld pilotbeleid. `MAX_ROAST_DAYS` stelt de bovengrens in. De laagste limiet van platform, roaster en product is van toepassing. Zowel bij bestellen als verzenden wordt de batch gecontroleerd. Bij een inmiddels oude toegewezen batch kan beheer een verse batch reserveren; de oorspronkelijke belofte blijft ongewijzigd. Rustadvies staat los van het verzendbeleid.

Betaalstatus, bestelstatus en verzendstatus staan apart. Alleen een intern door de betaaladapter bevestigde betaling kan worden doorgezet. Een GET of browserredirect kan nooit betaling bevestigen. Elke verwerking is transactioneel; betaalgebeurtenissen hebben unieke sleutels. Overdracht en shipment-aanmaak zijn herhaalbaar zonder dubbele verzendingen. De demo-overdracht gebeurt synchroon zodat de beheerstappen direct zichtbaar zijn; echte netwerkoverdracht hoort later in een queue-job met begrensde retries en unieke orderreferentie.

## Integratiegrenzen

`App\Contracts\PaymentGateway` en `App\Contracts\RoasterGateway` hebben werkende demo-implementaties. Er zijn **geen live adapters**. `/webhooks/mollie` is gereserveerd en weigert live verkeer. Bij een latere integratie moet een afzonderlijke webhookcontroller de betaalstatus zelf bij Mollie ophalen en bedrag, valuta, order en idempotentie controleren. Een marketplace vraagt daarnaast expliciete keuzes over verkopersrol, commissie en Mollie Connect/routing. Externe IDs horen in `external_mappings`; verwerkte gebeurtenissen in `integration_events`.

Een toekomstige Shopify-adapter kan product/variant/order-mappings en GraphQL Admin API gebruiken. Batchinformatie en leidend systeem moeten per roaster worden afgesproken. Geen tweede betaling of extra orderbevestiging bij import van een al betaalde order. Netwerkjobs, webhookverificatie, refunds en commerciële afrekening zijn nog niet geïmplementeerd.

Mail gebruikt lokaal de `log`-driver (`storage/logs/laravel.log`); er worden geen klantmails verzonden. Elke order heeft een lokale, afgeschermde tekstpreview van een bevestiging. De wachtwoordherstelflow van de starterkit schrijft de herstellink eveneens naar de lokale log.

## Testen

```sh
php artisan test --compact
npm run build
```

De gewone tests gebruiken een geïsoleerde SQLite-geheugendatabase voor snelle functionele checks. Ze testen autorisatie, registratie/inloggen met behoud van de mand, filteren, recepten, reviews, moderatie, voorraad, totalen, momentopnames, dubbele checkout/events, verlopen reserveringen en de volledige demo-orderketen. PostgreSQL blijft de applicatie- en hostingdatabase.

Voer de volledige suite ook op een afzonderlijke PostgreSQL-testdatabase uit om database-specifiek gedrag en echt gelijktijdige kopers te testen:

```sh
docker exec zetwerk-postgres createdb -U zetwerk zetwerk_test
DB_CONNECTION=pgsql DB_DATABASE=zetwerk_test php artisan test --compact tests/Feature/PlatformTest.php tests/Feature/PostgresConcurrencyTest.php
```

De concurrencytest start twee losse PHP-processen die tegelijk de laatste zak bestellen. Exact één moet slagen; voorraad mag niet negatief worden. Deze test verwijdert testdata en weigert daarom een databasenaam die niet eindigt op `_test`. Draai de tests niet tegelijk tegen dezelfde testdatabase.

## Hosting en verificatie

Deze PHP-monoliet moet naar Laravel-geschikte hosting met PostgreSQL (bijvoorbeeld een eigen PHP-FPM-server of Laravel-hosting). Sites/Cloudflare Workers kan deze PHP-app niet uitvoeren; er is daarom geen misleidende statische publicatie gemaakt. Webroot is `public/`; maak `storage/` en `bootstrap/cache/` schrijfbaar, stel `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, databasecredentials en veilige sessiecookies in. Bouw assets, draai migraties en installeer een scheduler. Voor een openbare demo: laat de demobanner staan en gebruik geen echte persoonsgegevens.

De responsieve CSS, semantische formulieren, foutmeldingen, focusstijlen, skiplink en toetsenbordbedienbare navigatie zijn aanwezig. In deze omgeving was de ingebouwde browserhost niet beschikbaar: de pagina's en flows zijn via HTTP en tests geverifieerd, maar visuele controle op echte mobiele schermen en een volledige toetsenbord-/schermlezertest moeten nog worden gedaan voordat een pilot wordt vrijgegeven.

Technische basis gecontroleerd tegen [Laravel 13 release notes](https://laravel.com/framework/docs/13.x/releases). Exact geïnstalleerde versies zijn met `composer show --direct` op te vragen.
