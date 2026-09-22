# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [Versionnement sémantique](https://semver.org/lang/fr/).

---

## [Unreleased]

### Added

- **CI GitHub Actions** (`.github/workflows/ci.yml`) — exécute `composer test` (Pest) sur PHP 8.2/8.3/8.4 en matrice et `composer analyse` (PHPStan niveau 6, annotations inline sur les PR) à chaque push sur `main`/`develop` et sur chaque pull request. Jusqu'ici la suite de tests et l'analyse statique ne tournaient que si quelqu'un pensait à les lancer manuellement. Badge de statut ajouté au README.

### Fixed

- **`composer.lock` verrouillait des paquets Symfony `^8.1` qui exigent en réalité PHP ≥8.4.1, alors que `composer.json` annonce `"php": ">=8.2"`.** Invisible en local (poste de dev en PHP 8.5), révélé immédiatement par la CI qui vient d'être ajoutée : `composer install` échouait sur les jobs PHP 8.2/8.3. Les 8 paquets `symfony/*` sont rétrogradés vers `^7.0` (résolu en 7.4.x, compatible PHP 8.2+) ; `config.platform.php` fixé à `8.2.0` dans `composer.json` pour que toute résolution future de `composer.lock` respecte la borne basse annoncée, même sur un poste avec un PHP plus récent — c'est exactement ce qui a permis à cette incohérence de passer inaperçue jusqu'ici.

## [2.0.0] - 2026-09-22

### Security

- **`JWT_SECRET` vide ou trop court est désormais rejeté explicitement.** `JwtGuard` levait silencieusement une clé HMAC vide (`??  ''`) si `JWT_SECRET` n'était jamais configuré — n'importe qui pouvait forger des tokens valides. Vérifié maintenant en un seul point (`secret()`), avant le bloc `try/catch` de décodage pour que l'erreur de configuration ne soit jamais confondue avec un échec normal de token — et étendu à la longueur minimale (32 octets, requise par HS256), qu'une `DomainException` de `firebase/php-jwt` aurait sinon fait échouer silencieusement en "non authentifié".
- **`JwtGuard::createToken()` ne permet plus à `$claims` d'écraser les claims réservés** (`sub`, `iat`, `exp`, `iss`) — l'ordre de `array_merge()` était inversé.
- **Le chiffrement des secrets 2FA (`HasTwoFactor`) passe d'un XOR à clé statique à AES-256-GCM authentifié.** L'ancien schéma réutilisait la même clé dérivée pour tous les secrets de l'installation (cassable façon "two-time pad") et retombait sur la chaîne codée en dur `'ironflow-totp-key'` si `APP_KEY` était absent — visible dans le code source public du framework. `deriveTwoFactorKey()` échoue désormais explicitement si `APP_KEY` n'est pas configuré ; toute donnée chiffrée avec l'ancien schéma XOR ne peut plus être déchiffrée (aucune installation réelle n'existe dans ce dépôt framework-only, donc pas de migration nécessaire).
- **`SessionGuard::attempt()` exécute désormais toujours une vérification Argon2id réelle**, même quand aucune ligne utilisateur n'est trouvée (contre un hash factice constant), pour que le temps de réponse ne révèle plus si un compte existe.
- **`SessionManager` positionne enfin le flag `Secure` du cookie de session** (actif sauf en local/debug) — le docblock l'affirmait depuis le début, mais `cookie_secure` n'était jamais réellement configuré.
- **`SessionManager::csrfToken()` lève une exception si la session n'a pas démarré**, au lieu de retourner silencieusement `''` — un `_token` vide soumis par un attaquant aurait pu passer `hash_equals('', '')` si `StartSession` n'avait jamais tourné.
- **`ValidatorFactory` ne masque plus l'échec de résolution du `Connection`** — un `catch (\Throwable) {}` transformait toute panne en `$db = null`, ce qui fait passer silencieusement **toutes** les règles `unique:`/`exists:` (`ValidatorInstance` les traite comme réussies quand `$db` est absent).
- **Une règle de validation au nom inconnu lève désormais une exception** (`\InvalidArgumentException`) au lieu de passer silencieusement (`default => true` dans `ValidatorInstance::applyRule()`) — une faute de frappe dans une règle (ex. `requird`) n'est plus interprétée comme "validé".
- **Validation de nom centralisée dans les ~19 commandes `make:*`** (`Command::validClassName()`, appelée dans chaque commande, erreur propre via un `try/catch` central dans `execute()`) — empêche l'écriture de fichiers hors du dossier prévu via un `name`/`--event` contenant `../` ou des caractères cassant la syntaxe PHP générée. `class_basename()` (`src/Support/helpers.php`) coupe désormais aussi sur `/`, pas seulement `\`, fermant la faille précise identifiée dans `make:form-request`/`make:resource`.
- **`QueueManager::pop()` restreint `unserialize()` aux seules sous-classes de `Job`** (`allowed_classes` calculé dynamiquement via `get_declared_classes()`, après autoload forcé de la classe nommée dans le payload) — élimine un vecteur d'injection d'objet PHP si la table `jobs` était un jour atteignable par autre chose que `push()`/`later()`.
- **Le cast `encrypted` de `Model` retombait silencieusement en clair si `APP_KEY` était absent, et chiffrait avec AES-256-CBC non authentifié.** Même classe de faille que l'ancien schéma 2FA (ci-dessus) : `decryptCast()`/`encryptCast()` retournaient/stockaient la valeur brute sans la moindre erreur si `APP_KEY` n'était pas configuré — un modèle déclarant `$casts = ['ssn' => 'encrypted']` pouvait persister des données sensibles en clair pendant des mois sans que rien ne le signale. Les deux méthodes délèguent désormais à un nouveau helper partagé `Ironflow\Support\Crypto` (AES-256-GCM authentifié — même schéma que 2FA, dont la logique de chiffrement est extraite dans ce helper pour ne plus être dupliquée), qui échoue explicitement si `APP_KEY` est absent.

### Fixed

- **Le scope global de `SoftDeletes` n'était jamais activé.** `bootSoftDeletes()` n'était appelée par personne — `Model::boot()` était un stub jamais invoqué, sans mécanisme générique de boot des traits. Conséquence concrète : **les lignes soft-deleted restaient visibles dans toutes les requêtes** (`all()`, `find()`, relations...). `Model` gagne un vrai cycle de boot par classe (`bootIfNotBooted()`, appelé depuis `__construct()` et `query()`) qui invoque `boot()` puis tout `boot{NomDuTrait}()` détecté via une résolution récursive des traits utilisés (pattern Laravel `classUsesRecursive`/`traitUsesRecursive`). `SoftDeletes::withTrashed()` était en plus un no-op déguisé ("simplified" en commentaire) et `onlyTrashed()` entrait en conflit direct avec le scope une fois celui-ci actif (`deleted_at IS NULL AND deleted_at IS NOT NULL` — toujours zéro résultat) ; `ModelQueryBuilder`/`Model::query()` acceptent maintenant une liste de scopes globaux à exclure, ce qui corrige les deux.
- **Les contraintes de clé étrangère déclarées via `foreignId()->constrained()` n'étaient jamais créées.** `ForeignIdDefinition::registerForeign()` n'était appelée par personne — les tables se créaient sans intégrité référentielle, sans erreur. `Table::foreignId()` (ex-`Blueprint::foreignId()`) mémorise désormais chaque définition en attente ; `Schema::buildTable()` les finalise avant de lire les FK à générer.
- **`migrate --fresh` (drop toutes les tables) n'avait pas de confirmation**, contrairement à `migrate:fresh` qui protège la même action destructrice. `MigrateCommand` demande désormais confirmation (sauf `--force`), comme `MigrateFreshCommand`.
- **`migrate:status` découvrait les migrations différemment des trois autres commandes `migrate*`** (`glob()` ad hoc limité aux modules au lieu de `Migrator::discoverPaths()`) — n'affichait jamais les migrations de `database/migrations/` à la racine.
- **`make:model --module=X` n'a jamais créé le dossier cible** (le `mkdir` n'existait que dans la branche sans `--module`) — la commande échouait silencieusement à écrire le fichier dès que `modules/{X}/Models/` n'existait pas déjà.
- **`Migrator::run()`/`rollback()` enveloppent désormais chaque migration (up()/down() + l'écriture de suivi) dans une transaction** — une migration qui s'applique partiellement avant de lever une exception n'est plus enregistrée comme "ran", ce qui aurait fait échouer silencieusement le prochain `migrate` (skip d'une migration jamais réellement appliquée).
- **`QueryBuilder::min()`/`max()` ne castaient pas leur résultat en `float`**, contrairement à `sum()`/`avg()` — `min('age')` retournait un `int`/`string` brut du driver PDO selon le dialecte. Corrigé avec un cast conditionnel (`is_numeric($value) ? (float) $value : $value`) plutôt qu'un cast inconditionnel comme `sum()`/`avg()`, pour ne pas corrompre un `MIN()`/`MAX()` sur une colonne non-numérique (date, texte).
- **Bug de casse `--module` non corrigé sur 15 commandes `make:*` sur 16** — seule `make:controller` normalisait `--module` en PascalCase ; les autres écrivaient dans un dossier à la casse différente pour le même module (`modules/blog/` vs `modules/Blog/`), cassant l'autoload PSR-4 sur un filesystem sensible à la casse. Centralisé dans `Command::moduleOption()`.
- **L'auto-enregistrement dans `providers:` (isolation de module) ne concernait que `make:controller`** — étendu à `make:listener` et `make:policy` (classes résolues via `container->make()` à l'exécution, `Events/Dispatcher.php`/`Auth/Gate.php`) et `make:service` par cohérence, via `Command::registerAsProvider()` (extrait de `MakeControllerCommand`, partagé).
- **`BelongsToMany`/`HasManyThrough` contournaient entièrement `ModelQueryBuilder` et donc n'appliquaient jamais les scopes globaux** (dont `SoftDeletes`, réellement actif depuis le correctif ci-dessus) — contrairement à `HasOne`/`HasMany`/`BelongsTo`, qui passent déjà par `ModelQueryBuilder`. Les deux relations construisaient leur SQL à la main et hydrataient les modèles directement ; réécrites pour passer par `ModelQueryBuilder` + `join()`, comme les autres types de relation. Effet de bord positif : le callback de contrainte passé à `with(['relation' => fn($q) => ...])` était silencieusement ignoré pour ces deux types (le paramètre `$constraint` existait mais n'était jamais utilisé dans le corps) — désormais appliqué, comme pour `HasMany`/`BelongsTo`.
- **`HasManyThrough::eagerLoadCount()` (via `withCount()`) générait du SQL invalide** — la relation héritait du comportement par défaut de `Relation::eagerLoadCount()`, qui suppose que la clé étrangère vit directement sur la table de la relation ; pour une relation "through", elle vit sur la table intermédiaire. Surchargée avec une jointure dédiée, groupée sur la table intermédiaire.
- `BelongsToMany::detach(int|array $ids = null)` — dépréciation PHP 8.4 (paramètre implicitement nullable) corrigée en `int|array|null`.
- **`withCount()` bypassait aussi les scopes globaux, sur tous les types de relation, et générait du SQL invalide pour deux d'entre eux.** `Relation::eagerLoadCount()` (utilisée par `HasOne`/`HasMany`) construisait du SQL brut sans passer par `ModelQueryBuilder` — réécrite pour l'utiliser, donc désormais scope-aware comme `getResults()`/`eagerLoad()`. `BelongsTo::eagerLoadCount()` héritait de cette même méthode de base alors qu'elle référence une colonne absente de la table du modèle "owner" (la FK vit sur la table enfant, pas sur celle du related) — aurait fait échouer `withCount()` sur n'importe quelle relation `belongsTo` avec une erreur SQL ; remplacée par un calcul local (0 ou 1 selon que la FK est renseignée), sans requête. `BelongsToMany`/`HasManyThrough` (déjà réécrites ci-dessus pour `getResults()`/`eagerLoad()`) reçoivent le même traitement pour `eagerLoadCount()`, avec jointure vers la table related plutôt qu'un comptage brut de la table pivot/intermédiaire.
- **`firstOrCreate()`/`updateOrCreate()` : la fenêtre de course entre le SELECT et l'INSERT ne fait plus planter la requête.** Ces méthodes restent un SELECT puis un INSERT — ça ne peut être rendu réellement atomique que par une contrainte unique en base (aucun code applicatif seul ne peut le garantir) — mais si deux appels concurrents trouvent tous les deux "rien" et tentent tous les deux de créer la ligne, le perdant lève désormais une exception de conflit qui est interceptée pour relire la ligne que le gagnant vient de créer, au lieu de laisser planter tel quel une `UniqueConstraintViolationException` de Doctrine DBAL non gérée. Une vraie violation d'intégrité sans rapport avec une course (aucune ligne ne correspond aux critères de recherche même après la relecture) continue à se propager normalement.

### Removed

- Suppression complète du pattern Facade (`Ironflow\Support\Facade` et les 15 façades statiques concrètes : `Auth`, `Cache`, `Config`, `DB`, `Event`, `Gate`, `Http`, `Log`, `Mail`, `Notification`, `Queue`, `Router`, `Session`, `Validator`, `View`). Aucune n'était appelée depuis du code réel du framework — uniquement des exemples de documentation. Toute dépendance de service doit désormais passer par injection de constructeur.
- `Container::makeInternal()` — jamais utilisée, et son intention documentée ("résoudre sans vérification de module") devenait incompatible avec le nouveau mécanisme d'héritage de contexte.
- `JwtGuard::setRequest()` — aucun appelant nulle part dans le framework.

### Changed

- **L'isolation de modules est maintenant appliquée à l'exécution, pas seulement déclarée.** `Container::make()` propage désormais un contexte de module ambiant à travers toute chaîne de résolution transitive (dépendances de constructeur), y compris pour les singletons déjà mis en cache. Auparavant, `$callerModule` n'était jamais passé nulle part dans le framework — le contrôle d'accès cross-module documenté dans le README n'avait donc jamais d'effet réel.
- `php forge make:controller --module=X` ajoute désormais automatiquement le contrôleur généré aux `providers:` de son module (isolation opt-in par déclaration) et corrige un bug de casse sur `--module` (`blog` → `Blog`).
- `NotificationManager::toMail()` passe désormais explicitement le `Mailer` injecté à la notification (`toMail(object $notifiable, Mailer $mailer)`), remplaçant l'accès via la façade `Mail` supprimée.
- **`Controller`/`ApiController` reçoivent désormais `TemplateEngine`, `Router`, `Gate` et la `Request` courante par injection de constructeur.** `view()`, `redirect()`, `redirectToRoute()`, `back()`, `authorize()`, `can()`, `bouncer()` (Controller) et `serialize()`/`pageUrl()` (ApiController) utilisent ces dépendances injectées au lieu de `Application::getInstance()->getContainer()->make(...)` — même famille de problème que les façades supprimées (résolution globale ambiante, exceptions avalées silencieusement dans `back()`/`pageUrl()` en cas d'échec). `Response::view()`/`render()` et `RedirectResponse::route()` restent des échappatoires statiques documentées pour les contextes sans DI (même catégorie que le helper `app()`), mais les contrôleurs ne passent plus par elles.
- README : les exemples de routing utilisaient `Router::get(...)` (façade supprimée) — corrigés en `$router->get(...)`, reflétant l'usage réel dans `routes.php`. Corrections additionnelles : `imports: ['auth']` → FQCN, référence à un `ServiceProvider` inexistant supprimée, `Config::class` → `ConfigRepository::class`.
- **Sécurité HTTP unifiée sous `ShieldConfig`, inspirée d'AdonisJS Shield.** `SecurityHeaders` et `VerifyCsrfToken` reçoivent désormais un objet de config typé (`Ironflow\Http\Shield\ShieldConfig`, source unique `config/shield.php`) par injection de constructeur, au lieu de lire `Config\Repository` ambiante avec un `catch (\Throwable) {}` qui masquait toute panne de configuration (headers de sécurité silencieusement désactivés en cas d'erreur). `ThrottleRequests`, `HandleCors`, `MaintenanceMode`, `HotReloadMiddleware` reçoivent de même leur dépendance (`RateLimiter`, `Config\Repository`, `Application`) par constructeur plutôt que via `Application::getInstance()`.
- `FrameworkExtension` (Twig) résout désormais `Application`/`Router` une seule fois, à la construction, depuis le `Container` déjà injecté — au lieu de rappeler `Application::getInstance()` à chaque appel de `asset()`/`vite_asset()`. `funcCan()`/`cannot()` ne masquent plus une panne de résolution du `Gate` derrière un simple "accès refusé".
- `Schedule::command()` échappe désormais chaque token de la commande individuellement (pas seulement le binaire PHP et `forge`), et résout le chemin de base via `Application` injecté au lieu d'`Application::getInstance()`.
- **`Blueprint` renommé en `Table`** (`Schema::create('posts', function (Table $table) {...})`) — terminologie plus directe que le vocabulaire Laravel, cohérent avec la volonté de ne pas juste calquer un framework existant.
- `Model::getDispatcher()` et `HasPermission::can()` ne masquent plus un échec de résolution derrière un `catch (\Throwable) {}` silencieux — `getDispatcher()` gagne un vrai mécanisme de test (`Model::setDispatcher()`, symétrique à `setConnection()`) pour rester utilisable en isolation sans booter une `Application` complète ; `can()` fait maintenant la même distinction que `FrameworkExtension::funcCan()` (une panne du `Gate` doit être visible, pas confondue avec un refus légitime).
- `Filesystem\Storage` ne retombe plus silencieusement sur le disque `local`/le dossier temp système en cas d'échec de résolution du conteneur (3 `catch (\Throwable) {}` retirés).
- `AboutCommand`/`TinkerCommand` reçoivent `Container`/`Application` par constructeur au lieu d'`Application::getInstance()` ; `MakePolicyCommand` utilise le helper global `base_path()` au lieu d'une méthode privée dupliquée.

### Added

- `Command::validClassName()`, `Command::moduleOption()` et `Command::registerAsProvider()` — helpers partagés par toutes les commandes `make:*` (le dernier extrait de `MakeControllerCommand`).
- `Model::setDispatcher()` — override de test pour `Dispatcher`, symétrique à `setConnection()`.
- `php forge migrate --fresh --force` — bypass la confirmation pour les pipelines CI/CD non-interactifs qui invoquent le flag délibérément.
- `ModelQueryBuilder`/`Model::query(array $withoutGlobalScopes = [])` — permet d'exclure un scope global nommé pour une requête donnée (utilisé par `SoftDeletes::withTrashed()`/`onlyTrashed()`).
- Tests pour `SoftDeletes`, les contraintes FK de `Table`, `ValidatorFactory`, la règle de validation inconnue, `JwtGuard`, `HasTwoFactor`, `SessionGuard`, `QueueManager` (durcissement `unserialize`), `Migrator` (transactions), `ScheduledEvent::withoutOverlapping()`, `Router::getCurrentRoute()`, le hook `Pipeline::processException()`, `CacheManager`, `Config\Repository`, `Logger`, `Paginator`, `NotificationManager` — la plupart de ces composants n'avaient aucune couverture auparavant.
- `Ironflow\Support\Crypto` — helper de chiffrement symétrique partagé (AES-256-GCM authentifié, clé dérivée de `APP_KEY` via SHA-256). Utilisé par `HasTwoFactor` et par le cast `encrypted` de `Model`, qui avaient auparavant chacun leur propre implémentation (dont une vulnérable — voir Security).
- Tests pour `BelongsToMany`/`HasManyThrough` (jointures, colonnes pivot, eager loading, respect des scopes globaux, `withCount()`) et pour `Crypto`/le cast `encrypted` — ces relations et ce cast n'avaient jusqu'ici aucune couverture.
- Tests pour le respect des scopes globaux par `withCount()` sur `HasMany`/`BelongsTo` en plus de `BelongsToMany`/`HasManyThrough`, et pour le comportement de `firstOrCreate()`/`updateOrCreate()` en cas de conflit (course simulée via contrainte unique, et violation d'intégrité authentique correctement propagée).
- **Passe de documentation PHPDoc.** Ajout des docblocks de classe manquants sur les 30 commandes `make:*`/`migrate*` (Console/Commands) et les 6 middlewares qui n'en avaient aucun (`Authenticate`, `MaintenanceMode`, `RedirectIfAuthenticated`, `ShareErrorsFromSession`, `StartSession`, `TrimStrings`). Ajout de `@throws` sur ~25 fichiers où une méthode publique pouvait lever une exception sans que ce soit documenté (`Container::make()`, `AuthManager`, `Router`, `RouteCollection`, `Pipeline`, `Migrator`, `ModuleManager`, `Storage::disk()`, etc.). Ajout de formes de tableaux (`array<string, mixed>`, etc.) sur `Model::toArray()` et plusieurs méthodes de `Collection`. Corrections mineures : alignement `@param` non standard dans `ApiController::paginate()`, commentaires `//` en paramètre de constructeur remplacés par un docblock dans `BelongsTo`, et une incohérence trouvée en chemin — `HttpResponse::throw()` documentait `HttpException` alors qu'il lève en réalité `\RuntimeException`.

- `Container::resetModuleContext()` — filet de sécurité défensif pour les process long-running (ex. `Queue\Worker`), appelé entre deux jobs pour garantir qu'aucun contexte de module ne fuite d'une résolution avortée vers la suivante.
- **`Pipeline::carry()` gagne un 3ᵉ hook Django-inspired : `processException(Request, \Throwable, ...$params): ?Response`**, aux côtés de `processRequest`/`processResponse` pour les middlewares "hook-style". Une exception levée plus loin dans le pipeline peut être récupérée par un middleware englobant (retour d'une `Response`) ou continuer à se propager (retour `null`) — la réponse récupérée traverse ensuite normalement `processResponse`. Documenté dans le README, section Middlewares.
- `Ironflow\Http\Shield\ShieldConfig` — value object typé regroupant headers de sécurité, HSTS, CSP et exemptions CSRF, remplaçant les lectures de tableaux bruts dispersées entre les namespaces de config `middleware.*` et une lecture ambiante séparée.
- `Router::getCurrentRoute()` et la fonction Twig `current_route()` — la route courante était documentée dans le README (`current_route()`) mais jamais réellement implémentée (le global Twig `current_route` retournait toujours `null`, désormais supprimé au profit de la fonction).
- **`ScheduledEvent::withoutOverlapping()`** — protège une tâche planifiée contre l'exécution simultanée de deux instances (ex. une tâche lente sur un cron à la minute) via un verrou fichier (`flock`) scopé à la description de l'événement ; un process tué ou planté relâche le verrou automatiquement, sans logique d'expiration manuelle à maintenir. `Schedule::run()` rapporte `'skipped (overlapping)'` pour une exécution ignorée.

### À venir

- Internationalisation / traductions (`trans()`, fichiers de langue)
- WebSockets / diffusion temps réel
- Documentation complète avec recettes
- Lint statique (`module:lint`) pour détecter les violations de frontière de module au niveau du code source

---

## [1.0.0] — 2026-06-11

Première version publique d'IronFlow. Le noyau est complet et testé (91 assertions, 0 échec).

### Added

#### Conteneur DI

- Résolution automatique par réflexion sur les type hints
- Attribut `#[Injectable]` et `#[Inject('key')]` pour l'injection de scalaires
- Liaison explicite via `bind()`, `singleton()`, `instance()`
- Résolution avec surcharges ponctuelles (`make($class, ['Dep' => $obj])`)

#### Architecture modulaire HMVC

- Attribut `#[Module]` avec déclaration `imports`, `providers`, `exports`
- Graphe de dépendances orienté — tri topologique (Kahn) au boot
- Détection des cycles au démarrage avec message d'erreur lisible
- Isolation : un provider est privé par défaut, exposé seulement via `exports`
- Commandes `module:graph` et `module:graph --check` (vérification CI)

#### Routeur

- Verbes HTTP : `get`, `post`, `put`, `patch`, `delete`, `options`
- Paramètres nommés `{id}`, paramètres optionnels `{slug?}`
- Contraintes regex via `->where('id', '[0-9]+')`
- Routes nommées avec `->name()` et génération d'URL via `route()`
- Groupes avec `prefix`, `middleware`, `namespace`
- Routes ressource RESTful en une ligne (`resource()` → 7 routes)
- Erreurs HTTP structurées : 404 introuvable, 405 méthode non autorisée

#### ORM Active Record

- Modèle de base avec `$table`, `$fillable`, `$hidden`, `$casts`, `$timestamps`
- CRUD complet : `create()`, `find()`, `findOrFail()`, `all()`, `save()`, `delete()`
- `firstOrCreate()`, `updateOrCreate()`
- Suivi de la saleté des attributs via `isDirty()` / `getOriginal()`
- Casts : `bool`, `int`, `float`, `json`, `datetime`, enums PHP 8.1+
- Scopes locaux et globaux
- Soft deletes via trait `SoftDeletes`
- Relations : `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`
- Eager loading anti-N+1 via `with()`
- Événements de modèle : `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`

#### QueryBuilder

- Interface fluide : `where`, `whereIn`, `whereNotIn`, `orWhere`, `orderBy`, `limit`, `offset`
- Agrégats : `count`, `sum`, `avg`, `min`, `max`
- `select`, `pluck`, `toSql`, `when()`
- `insertGetId`, `update`, `delete`

#### Schema Builder & Migrations

- `Schema::create()`, `table()`, `drop()`, `dropIfExists()`
- Blueprint : `id`, `string`, `text`, `integer`, `bigInteger`, `boolean`, `decimal`, `enum`, `json`, `timestamp`, `timestamps`, `softDeletes`, `foreignId`, `constrained`
- Modificateurs : `nullable`, `default`, `unsigned`, `index`, `unique`
- Migrator : batch tracking, `migrate`, `rollback`, `fresh`

#### CLI Forge

- `serve`, `route:list`, `module:graph [--check]`
- `migrate [--fresh] [--seed] [--rollback]`, `db:seed [--class=]`
- `key:generate`, `jwt:secret`
- `make:module`, `make:controller`, `make:model`, `make:middleware`, `make:command`
- `make:service`, `make:event`, `make:listener`, `make:seeder`, `make:factory`
- `make:form-request`, `make:resource`, `make:component`, `make:policy`
- `down [--message=] [--retry=]`, `up`

#### Authentification

- Auth par session (login, logout, remember me)
- Auth JWT : génération, vérification, refresh tokens
- Hachage bcrypt via `Hash::make()` / `Hash::check()`
- Middlewares `auth` et `guest`

#### RBAC & Sécurité

- `Gate` : définition de capacités globales (`Gate::define()`, `Gate::allows()`, `Gate::denies()`)
- `Policy` : classes de politiques auto-découvertes par convention
- Traits : `HasRole`, `HasPermission`, `HasTwoFactor`, `Auditable`
- Module d'audit : traçabilité complète des actions sensibles
- 6 migrations RBAC : `roles`, `permissions`, `role_user`, `permission_role`, `permission_user`, `audit_logs`
- Middlewares : `HotReloadMiddleware`, `HandleCors`, `SanitizeInput`

#### Validation

- Règles : `required`, `email`, `min`, `max`, `in`, `not_in`, `confirmed`, `nullable`, `integer`, `url`, `boolean`, `regex`
- `FormRequest` avec injection automatique dans les contrôleurs
- Messages d'erreur par champ, méthode `validated()` filtrante

#### Bus d'événements

- `Dispatcher::listen()`, `dispatch()`, `until()`
- Arrêt de propagation via `return false`
- Auto-découverte des listeners via attribut `#[EventListener]`

#### Templates Twig

- Namespaces par module (`@blog/posts/index.html.twig`)
- Fonctions : `route()`, `asset()`, `csrf_field()`, `old()`, `errors()`, `auth_user()`, `is_auth()`
- Filtres : `time_ago`, `markdown`, `slug`, `money`, `truncate`
- View composers

#### Factories & Seeders

- `Factory` abstraite : `definition()`, `count()`, `state()`, `make()`, `create()`
- `FakeGenerator` — wrapper `fakerphp/faker` avec helper `password()` (bcrypt)
- `Seeder` abstraite : `run()`, `call()` pour l'enchaînement

#### Tests

- Suite Pest v3, 91 assertions, 0 échec
- Fixtures PSR-4 conformes dans `tests/Unit/Fixtures/`
- `TestCase` avec helpers HTTP et assertions (`assertStatus`, `assertOk`, `assertRedirect`)
- Trait `RefreshDatabase` pour les tests avec base de données

---

[Unreleased]: https://github.com/ironflow-framework/framework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ironflow-framework/framework/releases/tag/v1.0.0
