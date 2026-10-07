# Losstaande wedstrijdplanner — ontwerp

**Datum:** 2026-10-07
**Bron:** `~/Herd/aetos` (module "Wedstrijdzaken", `app/**/Wedstrijdzaken/*`, tabellen `planner_*`)
**Doel:** de wedstrijdplanner van Aetos als zelfstandig, multi-tenant SaaS-product voor volleybalverenigingen, met behoud van alle functionaliteit.

## 1. Doel en context

De planner is in Aetos gebouwd als onderdeel van de clubwebsite. Er is kans om hem aan andere verenigingen te verkopen. Daarvoor komen er twee producten:

1. **Losse planner (dit project):** één centrale installatie, alle clubs als tenant.
2. **Planner + site (later, apart project):** één installatie per club, inclusief website. Aetos gaat later naar deze variant.

Dit project bouwt alleen product 1, maar zo dat de plannercode later naar een Composer-package kan voor product 2.

**Succescriteria**

- Alle planner-functionaliteit uit Aetos werkt, met uitzondering van wat in §9 buiten scope staat.
- Code en database zijn volledig Engels; de UI is Nederlands via `lang/nl`.
- Niets is Aetos-specifiek: clubnaam, teamherkenning, logo, kleur, tagline en dienstregels zijn instellingen.
- Een club ziet nooit data van een andere club (bewezen met tests).
- Modules zijn per club aan/uit te zetten.
- De overgenomen tests (~610) en de nieuwe tenancy-/moduletests zijn groen.

## 2. Beslissingen

| Onderwerp | Beslissing |
|---|---|
| Levering | Multi-tenant SaaS (één installatie, `club_id`). De latere +site-variant wordt single-tenant. |
| Codestructuur | Gewone Laravel-app met strakke grens: alle plannercode in `app/Planner/`, Filament via één `PlannerPlugin`. Later uit te pakken als package. |
| Sponsors | Niet in de planner. Het dagschema en de exports krijgen een uitbreidingspunt. |
| Aetos-data | Geen migratie nu. Het naamschema (§6) dient later als vertaalsleutel. |
| Onboarding | Platformbeheerder maakt clubs aan in een platformpaneel. Geen zelfregistratie of betaling. |
| Publieke URL's | Pad per club: `/{club}/wedstrijden`. Embed-weergave via `?embed=1`. |
| Queue | Database-driver; queuenaam via `ClubQueue::for($club)`, configureerbaar gedeeld of per club. |
| Taal | Code/DB Engels, URL's en UI Nederlands. |
| Modules | Kern plus optionele modules per club (§5). Pakketten (`plans`) later. |

## 3. Architectuur

### 3.1 Stack

PHP 8.5, Laravel 13, Filament 5, Livewire 4, `bezhansalleh/filament-shield` + `spatie/laravel-permission` (teams-modus, team key `club_id`), `spatie/laravel-activitylog`, `barryvdh/laravel-dompdf`, `spatie/browsershot`, `spatie/laravel-backup`, `stechstudio/filament-impersonate`.

Dev: PHPUnit 13, Pint, Larastan, Rector (+ rector-laravel), laravel/boost, pail, ide-helper. Frontend: Vite, Tailwind, Alpine, interact.js (dagplanner).

Niet overnemen: Mollie, spatie/translatable, versionable, Flux, Volt, Google Drive-flysystem, passkeys, intervention/image, endroid/qr-code, jenssegers/agent, de mediabibliotheek.

### 3.2 Mappenstructuur

```
app/
  Models/            Club, User                      ← platform
  Filament/Platform/ platformpaneel (clubs, users)   ← platform
  Providers/Filament/PlatformPanelProvider, ClubPanelProvider
  Support/Tenancy/   CurrentClub, BelongsToClub, ResolveClubFromRoute, ClubQueue
  Planner/
    Models/ Enums/ Services/ Support/ Policies/
    Console/ Jobs/ Http/ Livewire/ Exceptions/ Observers/
    Filament/ (Clusters, Resources, Pages, Widgets, Concerns)
    PlannerPlugin.php        ← registreert alle Filament-onderdelen
    PlannerServiceProvider.php ← routes, views, lang, commands, schedule
    Features/PlannerFeature.php
resources/views/planner/     resources/css/planner.css  resources/js/planner.js
lang/nl/planner.php (+ lang/nl/planner/*.php per domein)
routes/planner.php           ← publieke clubroutes, geladen door PlannerServiceProvider
tests/Feature/Planner/  tests/Unit/Planner/  tests/Feature/Platform/
```

Regel: code in `app/Planner` mag `App\Models\Club`, `App\Models\User` en `App\Support\Tenancy\*` gebruiken, maar niets anders buiten `app/Planner`. Code buiten `app/Planner` gebruikt de planner alleen via `PlannerPlugin`, `PlannerServiceProvider` en `PlannerFeature`. Een architectuurtest bewaakt dit.

### 3.3 Panelen en rollen

- **Platformpaneel** `/admin`: alleen gebruikers met `users.is_platform_admin`. Beheert clubs (naam, slug, actief, modules), gebruikers, en nodigt de eerste clubbeheerder uit (uitnodigingsmail met wachtwoord-instellen-link). Impersonate vanuit hier.
- **Clubpaneel** `/app/{club:slug}`: Filament-tenancy op `Club`, `User::getTenants()` via pivot `club_user`. Registreert `PlannerPlugin`. Bevat ook een eenvoudige gebruikersbeheer-resource voor de clubbeheerder (gebruikers uitnodigen, rol toekennen).
- **Rollen per club** (Shield, teams op `club_id`): `club_admin` (alle planner-rechten + gebruikers + instellingen) en `planning_committee` (equivalent van Aetos' `zaalcommissie`). Rechtformaat zoals Aetos: `{ViewAny|View|Create|Update|Delete|DeleteAny}:{Model}`, `View:{Page}`, `View:{Widget}`. Seeder maakt beide rollen bij het aanmaken van een club (via `ClubObserver`).

### 3.4 Tenancy

- `clubs`: `id, name, slug (uniek), is_active, features (json), timestamps`.
- `club_user`: `club_id, user_id, timestamps`.
- Elke `planner_*`-tabel heeft `club_id` (FK `clubs`, cascade on delete, index).
- `BelongsToClub`-trait: global scope op `CurrentClub::id()` en vult `club_id` bij `creating`. Vervangt Aetos' `BelongsToTenant`.
- `CurrentClub`: één bron van de actieve club. Gevuld door Filament-tenant (paneel), `ResolveClubFromRoute`-middleware (publiek; 404 bij onbekende of inactieve club), of expliciet `CurrentClub::run($club, fn)` in jobs/commands. Zonder actieve club gooit de scope een exception in plaats van alle data te tonen.
- `ClubObserver::deleting` ruimt plannerdata op (vervangt `SitePlannerOpruimer`).
- Uitbreiding naar eigen domein later = extra resolver in `CurrentClub`; geen codewijziging elders.

### 3.5 Publieke routes

Middleware `web`, `ResolveClubFromRoute`, `feature:<module>` waar nodig.

| URL | Doel | Module |
|---|---|---|
| `/{club}/wedstrijden` | gepubliceerd wedstrijdoverzicht (Livewire) | kern |
| `/{club}/wedstrijd-verzetten` | verzoek wedstrijd verzetten | `change_requests` |
| `/{club}/dienst-wijzigen` | verzoek dienst wijzigen | `change_requests` + `duties` |
| `/{club}/rss`, `/rss/thuis`, `/rss/sporthal/{naam}`, `/rss/team/{code}`, `/rss/dag/{datum}` | feeds | `rss_feeds` |

Throttling zoals Aetos (20/60 formulieren, 30/min feeds). Formulieren behouden reCAPTCHA en rate-limiting (`RecaptchaService`, `SubmissionRateLimiter` gaan mee naar `app/Planner/Support/Security`; reCAPTCHA-sleutels via `.env`, uit te zetten). RSS-namespace wordt `xmlns:planner="{APP_URL}/rss/ns"`. `?embed=1` geeft een layout zonder header/footer en met `frame-ancestors *`. De basis-layout toont clublogo en primaire kleur uit de instellingen.

### 3.6 Achtergrondwerk

- Scheduler: dagelijks 06:00 `planner:dispatch-nevobo-sync` → per actieve club een `SyncNevoboPlanning`-job.
- Jobs implementeren `ShouldQueue`, gebruiken `->onQueue(ClubQueue::for($club))`, `WithoutOverlapping("club-{$id}")`, een timeout, en draaien binnen `CurrentClub::run`.
- `config/planner.php`: `queue.mode = shared|per_club` (default `shared`, queue `planner`; `per_club` → `club-{slug}`).
- De zes Aetos-commands (`wedstrijdzaken:*`) worden `planner:*` met verplichte `--club=` (of `--all`).

## 4. Aetos-specifieke waarden → instellingen

| Aetos (hardcoded) | Nieuw |
|---|---|
| `AETOS`/`AET`-regexen in `Teamcode`, `ImportService` (thuis/uit), `ConflictEngine`, `Ftz/RuleEngine`, `DienstenImportService` | `planner_settings.team_name_patterns` (json lijst van prefixen, bv. `["AETOS","AET"]`), via één `ClubTeamMatcher` |
| `AETOS XZ 1` in `ZitvolleybalService` | afgeleid van `team_name_patterns` + teamcode |
| User-agent `AETOS-wedstrijdplanner/1.0` | `{APP_NAME}/1.0` |
| `logo-aetos.svg/png` in `RendertMetChrome::clublogo()` | `planner_settings.logo_path` (upload, public disk) met neutrale fallback |
| Tagline "Aetos ▪ Samen ▪ Sportief ▪ Sterker" | `planner_settings.schedule_tagline` (nullable) |
| Kleur `#00aade` (CSS, uitwedstrijden, feed) | `planner_settings.primary_color`, als CSS-variabele `--planner-primary` |
| FTZ-defaults `DS 10`, `HS 2/3`, `HS 1/DS 1` in `FtzConfiguratie` | leeg als default; geconfigureerd via bestaande `duty_rules` (was `ftz_configuratie`) |
| Arnhemse sporthallen/teams in seeders | geen; alleen een `DemoClubSeeder` voor lokaal |
| Clubnaam in teksten (uitwedstrijden, feeds) | `clubs.name` |

`planner_settings.clubnaam` vervalt: de clubnaam is `clubs.name`.

## 5. Modules

`App\Planner\Features\PlannerFeature` (backed enum), opgeslagen in `clubs.features` (json lijst). Check: `$club->hasFeature(PlannerFeature::Duties)`, middleware `feature:duties`, trait `RequiresPlannerFeature` op pages/resources/widgets (`canAccess` + navigatie).

**Kern (altijd aan):** teams, sporthallen/velden/aliassen, beschikbaarheid, kalender, activiteiten, versies, thuiswedstrijden-dagplanner + conflictcontrole + voorstellen + voorlopige plaatsing, import (Excel/RSS), Nevobo-sync en -wijzigingen, sporthalbezetting, exports, publiek wedstrijdoverzicht, instellingen.

| Module | Inhoud | Vereist |
|---|---|---|
| `away_matches` | Uitwedstrijden-pagina, uit-wijzigingen, widget | — |
| `duties` | scheidsrechter/teller-toewijzing, zaaldiensten, FTZ-planner, takendashboard, takenoverzicht-export, diensten-import | — |
| `volunteer_points` | punten per dienst, punten per team, percentages, puntkolommen | `duties` |
| `volleystars` | VolleyStars-blokken/-taken, op dagschema en takendashboard | `duties` |
| `trainings` | trainingen + import, meetellen in bezetting | — |
| `change_requests` | publieke verzoekformulieren, verzoekenresource, widget | — |
| `locker_rooms` | kleedkamerindeling op dagschema | — |
| `sitting_volleyball` | zitvolleybal-logica | — |
| `rss_feeds` | publieke RSS-feeds | — |

Uitgeschakelde module: niet in navigatie, 403/404 op directe URL, services slaan berekening over, kolommen/formuliervelden/dagschema-onderdelen verborgen. Afhankelijkheden staan in de enum (`requires()`) en worden in het platformpaneel afgedwongen. Een latere `plans`-tabel levert alleen de lijst modules; de check verandert niet.

## 6. Naamschema

Tabelprefix `planner_` blijft. Elke tabel: `site_id` → `club_id`. `created_at/updated_at` ongewijzigd. Statusconstanten worden backed enums in `app/Planner/Enums` met Nederlandse labels via `HasLabel`.

### 6.1 Tabellen en modellen

| Aetos tabel | Nieuwe tabel | Model |
|---|---|---|
| planner_sporthallen | planner_venues | Venue |
| planner_velden | planner_courts | Court |
| planner_sporthal_aliases | planner_venue_aliases | VenueAlias |
| planner_teams | planner_teams | Team |
| planner_team_bronnen | planner_team_sources | TeamSource |
| planner_versies | planner_schedule_versions | ScheduleVersion |
| planner_wedstrijden | planner_matches | FixtureMatch (`Match` is gereserveerd in PHP) |
| planner_beschikbaarheid | planner_availabilities | Availability |
| planner_kalender_activiteiten | planner_calendar_events | CalendarEvent |
| planner_activiteit_kleuren | planner_activity_colors | ActivityColor |
| planner_activiteiten | planner_activities | Activity |
| planner_zaaldiensten | planner_hall_duties | HallDuty |
| planner_volleystars_blokken | planner_volleystars_blocks | VolleystarsBlock |
| planner_volleystars_taken | planner_volleystars_tasks | VolleystarsTask |
| planner_nevobo_verwerkingen | planner_nevobo_changes | NevoboChange |
| planner_trainingen | planner_trainings | Training |
| planner_wedstrijdverzoeken | planner_change_requests | ChangeRequest |
| planner_user_preferences | planner_user_preferences | UserPreference |
| planner_settings | planner_settings | PlannerSetting |
| planner_team_sponsor | — (vervalt) | — |

### 6.2 Kolommen

Gemeenschappelijk: `naam`→`name`, `plaats`→`city`, `adres`→`address`, `actief`→`is_active`, `bron`→`source`, `volgorde`→`sort_order`, `opmerkingen`→`notes`, `notitie`→`note`, `datum`→`date`, `tijd`→`time`, `status`→`status`, `kleur`→`color`, `titel`→`title`, `sporthal_id`→`venue_id`, `veld_id`→`court_id`, `versie_id`→`version_id`, `begintijd`→`start_time`, `eindtijd`/`eind_tijd`→`end_time`, `start_tijd`→`start_time`, `weekdag`/`dag`→`weekday`, `created_by` ongewijzigd.

**planner_venues:** zaalcode→venue_code, naam→name, plaats→city, adres→address, beheerd→is_managed, ftz→has_duties, kleedkamers_op_dagschema→show_locker_rooms_on_schedule, voor_wedstrijden→for_matches, voor_trainingen→for_trainings, bron→source, actief→is_active.

**planner_courts:** sporthal_id→venue_id, naam→name, trainingsveld→is_training_court, bron→source, volgorde→sort_order, actief→is_active.

**planner_venue_aliases:** sporthal_id→venue_id, naam→name.

**planner_teams:** code, naam→name, bron→source, standaard_dag→default_weekday, standaard_tijd→default_time, standaard_hal_id→default_venue_id, standaard_veld→default_court, standaard_wedstrijdduur→default_match_duration, speelt_op_2_velden→plays_on_two_courts, telt_eigen_wedstrijd→scores_own_match, levert_scheidsrechter→provides_referee, nevobo_fluit→nevobo_referee, ftz→has_duties, meenemen_ftz→include_in_duties, vrijwilligers→volunteers, meenemen_vrijwilligers→include_volunteers, vp_verbruikt→volunteer_points_used, vp_geleverd→volunteer_points_earned, vrijwilligerspunten_percentage→volunteer_points_percentage, kleedkamer_categorie→locker_room_category.
Vervallen (website): pagina_naam, slug, toon_in_overzicht, heeft_pagina, uitgelicht, uitgelicht_volgorde, niveau, foto, foto_bijschrift, intro, instagram_url, facebook_url, spelers, content_blocks, meta_title, meta_description.

**planner_team_sources:** team_id, naam→name, url, type, volgorde→sort_order, actief→is_active, laatst_gecontroleerd_op→last_checked_at, laatst_succesvol_op→last_succeeded_at, laatste_fout→last_error.

**planner_schedule_versions:** naam→name, notitie→note, status, published_flag→is_published, created_by, published_at, archived_at.

**planner_matches:** versie_id→version_id, datum→date, tijd→time, duur_min→duration_minutes, team_thuis→home_team_name, team_uit→away_team_name, team_thuis_id→home_team_id, team_uit_id→away_team_id, poule→pool, wedstrijdcode→match_code, regio→region, wedstrijdtype→match_type, sporthal_id→venue_id, veld_id→court_id, veld_id_2→second_court_id, voorlopig_datum→provisional_date, voorlopig_tijd→provisional_time, voorlopig_duur_min→provisional_duration_minutes, voorlopig_sporthal_id→provisional_venue_id, voorlopig_veld_id→provisional_court_id, voorlopig_veld_id_2→provisional_second_court_id, scheidsrechter→referee, scheidsrechter_auto→referee_auto, nevobo_scheids→nevobo_referee, nevobo_status, nevobo_wijziging_status→nevobo_change_status, nevobo_gewijzigd_op→nevobo_changed_at, nevobo_oude_datum→nevobo_previous_date, nevobo_oude_tijd→nevobo_previous_time, nevobo_oude_sporthal→nevobo_previous_venue, nevobo_nieuwe_datum→nevobo_new_date, nevobo_nieuwe_tijd→nevobo_new_time, nevobo_nieuwe_sporthal→nevobo_new_venue, teller→scorer, teller_auto→scorer_auto, opmerkingen→notes, origineel_datum→original_date, origineel_tijd→original_time, origineel_sporthal→original_venue, origineel_veld→original_court, wijziging_status→change_status, wijziging_aangevraagd→change_requested, wijziging_aangevraagd_op→change_requested_at, wijziging_aangevraagd_door→change_requested_by, uit_wijziging_status→away_change_status, uit_nieuwe_datum→away_new_date, uit_nieuwe_tijd→away_new_time, uit_nieuwe_sporthal→away_new_venue, uit_opmerkingen→away_notes, is_zitvolleybal→is_sitting_volleyball, voorstel_veld_id→suggested_court_id, voorstel_duur_min→suggested_duration_minutes, created_by.

**planner_availabilities:** sporthal_id→venue_id, veld_id→court_id, scope, datum→date, weekdag→weekday, periode_start→period_start, periode_eind→period_end, begintijd→start_time, eindtijd→end_time, status, notitie→note.

**planner_calendar_events:** start_date, end_date, activity→name, type.

**planner_activity_colors:** type, kleur→color.

**planner_activities:** versie_id→version_id, datum→date, tijd→start_time, eind_tijd→end_time, titel→title, sporthal_id→venue_id, veld_id→court_id, opmerkingen→notes.

**planner_hall_duties:** versie_id→version_id, datum→date, start_tijd→start_time, eind_tijd→end_time, team_id, team_auto→team_auto, sporthal_id→venue_id, opmerkingen→notes.

**planner_volleystars_blocks:** versie_id→version_id, datum→date, sporthal_id→venue_id, opbouw_start→setup_start, opbouw_eind→setup_end, afbouw_start→teardown_start, afbouw_eind→teardown_end, tellen_start→scoring_start, tellen_eind→scoring_end, opmerkingen→notes.

**planner_volleystars_tasks:** blok_id→block_id, rol→role, team_id, veld_id→court_id, volgorde→sort_order.

**planner_nevobo_changes:** versie_id→version_id, wedstrijd_id→match_id, veld→field, oude_waarde→old_value, nieuwe_waarde→new_value, verwerkt→is_processed, verwerkt_op→processed_at, verwerkt_door→processed_by.

**planner_trainings:** team_id, sporthal_id→venue_id, dag→weekday, begintijd→start_time, eindtijd→end_time, veld→court, type_veld→court_type, opmerkingen→notes, bron→source, created_by.

**planner_change_requests:** wedstrijd_id→match_id, zaaldienst_id→hall_duty_id, wedstrijdcode→match_code, team_id, soort→kind, ftz_dienst→duty_type, dienst_datum→duty_date, dienst_tijd→duty_time, dienst_locatie→duty_location, dienst_omschrijving→duty_description, naam→name, email, telefoon→phone, contactvoorkeur→contact_preference, reden→reason, reden_overig→reason_other, toelichting→explanation, voorgestelde_datum→proposed_date, voorgestelde_tijd→proposed_time, status, notitie→note, behandeld_op→handled_at, behandeld_door→handled_by. (Versleutelde attributen blijven versleuteld.)

**planner_user_preferences:** user_id, default_sporthal_id→default_venue_id, actieve_versie_id→active_version_id, geselecteerde_datum→selected_date, verborgen_hallen→hidden_venues, nevobo_groepering→nevobo_grouping, toon_trainingen→show_trainings, ftz_vraag_reden→duties_ask_reason, ftz_validatiemodus→duties_validation_mode, overlap_beleid→overlap_policy, publiceren_met_conflicten→publish_with_conflicts.

**planner_settings:** hoofdsporthal_id→main_venue_id, verzoek_open→requests_open, verzoek_redenen→request_reasons, ftz_verzoek_redenen→duty_request_reasons, seizoen_start→season_start, seizoen_eind→season_end, competitie_start→competition_start, competitie_eind→competition_end, training_periode_start→training_period_start, training_periode_eind→training_period_end, buffer_min→buffer_minutes, cache_ttl_min→cache_ttl_minutes, vp_fluitdienst→points_referee_duty, vp_teldienst→points_scorer_duty, vp_zaaldienst→points_hall_duty, vp_volleystars→points_volleystars, zaaldienst_weekdagen→hall_duty_weekdays, zaaldienst_uitzonderingen→hall_duty_exceptions, team_groep_volgorde→team_group_order, export_dagschema_footer→schedule_footer, ftz_configuratie→duty_rules, admin_override_extern_fout→admin_override_external_error.
Vervallen: clubnaam (→ `clubs.name`), team_uitgelicht_titel, team_intro (website).
Nieuw: team_name_patterns (json), logo_path, primary_color, schedule_tagline.

### 6.3 Enumwaarden

| Enum | Aetos → nieuw |
|---|---|
| MatchType | thuis→home, uit→away |
| ChangeStatus (match) | aangevraagd→requested, te_wijzigen→to_change |
| AwayChangeStatus | geen→none, loopt→in_progress, overleg→in_discussion, definitief→final, afgelast→cancelled |
| NevoboChangeStatus | geen→none, doorgevoerd→applied, conflict→conflict |
| ScheduleVersionStatus | concept→draft, gepubliceerd→published, gearchiveerd→archived |
| AvailabilityScope | datum→date, weekdag→weekday, periode→period |
| AvailabilityStatus | beschikbaar→available, gedeeltelijk→partial, gesloten→closed |
| ChangeRequestStatus | open→open, geaccepteerd→accepted, afgewezen→rejected, geparkeerd→parked |
| ChangeRequestKind | wedstrijd→match, ftz→duty |
| DutyType | fluiten→referee, tellen→scorer, zaaldienst→hall_duty |
| ContactPreference | email→email, whatsapp→whatsapp |
| ChangeReason | (lijst uit settings), overig→other |
| OverlapPolicy | niet_toestaan→deny, toestaan_met_waarschuwing→warn, altijd_toestaan→allow |
| TeamSourceType | programma→schedule, stand→standings, resultaten→results |
| NevoboChangeField | datum→date, tijd→time, sporthal→venue, veld→court |
| VolleystarsRole | opbouwen→setup, afbouwen→teardown, tellen→scoring |
| Source | handmatig→manual, wedstrijdimport→match_import (overige bronwaarden 1-op-1 vertalen tijdens het overzetten) |

Weekdagen blijven integers (0 = zondag, zoals Aetos).

### 6.4 Code

Klassen, methodes, variabelen, routenamen, permissienamen, activitylog-namen (`planner-*`) en vertaalsleutels in het Engels. Voorbeelden: `Thuiswedstrijden` → `HomeMatchesPlanner`, `Uitwedstrijden` → `AwayMatches`, `FtzPlanner` → `DutyPlanner`, `TakenDashboard` → `DutiesDashboard`, `Sporthalbezetting` → `VenueOccupancy`, `NevoboWijzigingen` → `NevoboChanges`, `PlannerInstellingen` → `PlannerSettings`, `ProposalFinder` blijft, `VoorlopigeVerplaatsing` → `ProvisionalMove`, `DagschemaAfbeelding` → `DayScheduleImage`, `TakenoverzichtAfbeelding` → `DutyOverviewImage`, `LockerRoomPlanner` blijft, `PublicatieGeblokkeerd` → `PublishBlocked`, `Support/Halnaam` → `VenueName`, `Wedstrijdcode` → `MatchCode`, `Dagschemaraster` → `DayScheduleGrid`, `Rooster` → `Timetable`. De volledige lijst wordt per fase in het implementatieplan vastgelegd.

Nevobo-specifieke termen (Nevobo, poule, VolleyStars) blijven herkenbaar.

## 7. Uitbreidingspunt dagschema/exports

`PlannerPlugin::scheduleFooterUsing(Closure $renderer)` en een blade-stack `@stack('planner.schedule.footer')`. Standaard leeg. Gebruikt door het dagschema (PNG/PDF), het takenoverzicht en de planning-export. Hiermee kan de latere sponsor-package of +site-variant een sponsorrij toevoegen zonder plannercode te wijzigen.

## 8. Aanpak van het overzetten

Verse Laravel 13-installatie in deze map. Per fase: bestanden uit Aetos overnemen, volledig naar §6 hernoemen, Aetos-waarden vervangen door §4, Nederlandse teksten naar `lang/nl`, bijbehorende tests overnemen en hernoemen. Een fase is klaar als de tests groen zijn en Pint/Larastan schoon.

1. **Fundament:** skeleton, tooling, `clubs`/`club_user`/users, platform- en clubpaneel, tenancy (`CurrentClub`, `BelongsToClub`, middleware), Shield-rollen per club, `PlannerFeature`, `ClubQueue`, `config/planner.php`, `planner_settings`, `PlannerPlugin`/`PlannerServiceProvider`, architectuurtest.
2. **Stamdata:** venues, courts, aliassen, teams, teambronnen, kalender, activiteitkleuren + resources.
3. **Planning-kern:** versies, matches, beschikbaarheid, activiteiten, ConflictEngine, AvailabilityChecker, ProposalFinder, ProvisionalMove, VersionService, dagplanner (pagina + CSS/JS), user preferences, publicatie met conflicten.
4. **Import & Nevobo:** SpreadsheetReader/Writer, ImportService (+ RSS, cleanup, beschikbaarheid), NevoboRssService, NevoboChanges-pagina, sync-job per club, `planner:*`-commands.
5. **Exports:** ExportService, dagschema (PNG via Browsershot, PDF via dompdf), sporthalbezetting, uitbreidingspunt §7.
6. **Modules:** away_matches, duties (incl. FTZ-planner, takendashboard, diensten-import, takenoverzicht), volunteer_points, volleystars, trainings, change_requests, locker_rooms, sitting_volleyball, rss_feeds.
7. **Publiek:** wedstrijdoverzicht, embed-weergave, basis-layout met clubbranding.

## 9. Buiten scope

Sponsors en team-sponsorkoppeling, teampagina's en hun velden, Super Saturday-boekje, mediabibliotheek, page-blocks, `NevoboFeed`-websiteblok, zelfregistratie, betaling, `plans`-tabel, Aetos-datamigratie, Engelse UI, eigen domein per club.

## 10. Testen

- PHPUnit 13, SQLite `:memory:`.
- Overgenomen planner-tests uit `tests/Feature/Wedstrijdzaken` (84 bestanden) en de bijbehorende unit-tests, hernoemd naar `tests/Feature/Planner` / `tests/Unit/Planner`. Tests voor vervallen onderdelen (teampagina's, sponsors) vervallen mee.
- Test-helper `actingAsClubUser($role)` die club, gebruiker, rol en `CurrentClub` opzet.
- Nieuwe tests:
  - **Tenancy-isolatie:** per model, per publieke route en per job: club B-data nooit zichtbaar of wijzigbaar voor club A; scope zonder club gooit een exception.
  - **Modules:** uitgeschakelde module onzichtbaar in navigatie, directe URL geweigerd, service-uitvoer zonder module-data; afhankelijkheden afgedwongen.
  - **Clubinstellingen:** teamherkenning, thuis/uit-bepaling en zitvolleybal werken met andere `team_name_patterns` dan `AETOS`.
  - **Architectuur:** `app/Planner` gebruikt niets buiten de toegestane klassen.
  - **Platform:** club aanmaken maakt rollen aan; uitnodiging; alleen platformbeheerders in `/admin`.

## 11. Foutafhandeling

- Nevobo-fouten per teambron in `last_error` / `last_checked_at`, zoals Aetos.
- Sync-jobs per club geïsoleerd (timeout, `WithoutOverlapping`, failed jobs per club zichtbaar in de logs met `club_id` in de context).
- Publiceren met conflicten blokkeert via `PublishBlocked`, tenzij de gebruikersvoorkeur het toestaat.
- Onbekende of inactieve club op publieke route → 404.
