# Xentara — Platform Concept Description

> *Curated, not aggregated.*

## 1. Overview

Xentara is a **headless, AI-powered intelligence curation platform** built for human curators who want to surface signal without noise. It is designed around a deliberate, two-tier architecture:

| Tier | Repository | Standard Domain / Host | Role |
|------|-----------|------------------------|------|
| **Platform (Back-office)** | `xentara` | `studio.xentara.buzz` | Curator dashboard, AI pipeline, content ingestion, headless API |
| **Reader Client** | `xentara-client` | `xentara.buzz` | Consumer-facing reader app, PWA, white-label deployable |

The two are connected through a versioned REST API (`/api/v1`) and a shared TypeScript SDK (`@xentara/api-client`). The client never touches the database directly — all data flows through the platform API.

---

## 2. Core Philosophy

- **Curated, not aggregated** — Every article visible to readers has been reviewed and explicitly published by a human curator. Nothing surfaces automatically without human approval.
- **AI-assisted, human-decided** — AI processes and enriches content (summarisation, taxonomy, byline, sentiment), but the curator has full editorial control before publication.
- **White-label by design** — The reader client is a deployable shell that can be fully branded to any Hub, making it suitable for independent media outlets, communities, or corporate information services.
- **Signal over volume** — The platform is designed to produce high-quality, contextualised intelligence — not a raw news aggregator.

---

## 3. Key Concepts

### 3.1 Hub

A **Hub** is the fundamental organising unit of Xentara. Think of it as a curated intelligence channel or publication.

Each Hub has:
- A unique **slug** identifier and public-facing name
- An **owner** and optional team **members** (owner / editor / viewer roles)
- **Monitored Sources** — the RSS feeds, RSSHub paths, or YouTube channels it watches; each source has a configurable `default_article_type`, an `is_ingestion_paused` flag, and a `filter_before_date` date threshold for ignoring older discovered articles
- A curated **publication feed** — articles that have been reviewed and published
- Full **branding** — logo, hero media (static image, looping video, or crossfading slideshow — controlled by `hub_media_type`), custom colour palette, headline, byline, tagline
- Optional **Boards** — taxonomy-filtered sub-feeds for navigating content by topic (supporting custom filter modes and sub-boards mapped to Publication Group categories)
- **Reader Settings** — access control (public/private, non-subscriber viewing), subscription opt-in, card layout, services config with remote-persisted custom pages, publisher fields toggle, and a `listing_status` field (`listed` / `unlisted`) that controls visibility in the hub discovery directory
- **Distribution Channels** — connected Telegram groups or channels for push delivery
- **Engagement Config** — configurable reaction types and comment threads
- **Promotions** — hub-level announcements or campaign messages distributed to channels and/or the reader feed; supports `always_display_first` pinning for high-priority promos
- **Hub Announcements** — custom messages/announcements targeted at the hub's readers (`hub_message`)
- **Content Suggestions** — readers and visitors can submit source suggestions (publisher, YouTube channel, website, article, podcast, newsletter) for curator review
- **Placement Requests** — third parties can submit requests (advertisement, notice, event, sponsorship, announcement, promotion) for curator approval
- **Hired Assistants** — hubs can engage Curator Assistants from the platform directory, tracked with a weekly hours allocation

### 3.2 Publication

A **Publication** is a curated article within a Hub. It starts as a raw tracked URL and progresses through the intelligence pipeline before a curator publishes it.

Publication states: `raw → transcribing → analyzing → ready → published` (or `failed` / `auto_purge_tagged` / `purged`)

Each publication includes:
- Original source URL and monitored source attribution
- AI-generated **summary** (Markdown), **byline** (150 chars), **synopsis** (2–3 sentences), and **refined title**
- **Sentiment score** (–1.0 to 1.0) and multi-dimensional **taxonomy classifications**
- **Article type** (`text`, `video`, `podcast`) — inherited from the monitored source by default; displayed as a badge on reader cards
- **Original language** — detected by the AI; displayed as a language flag on reader cards
- Optional **curator commentary** ("Curator's Take") with a configurable label per hub
- Feature flag, archive flag, thumbnail and image gallery (with ingestion fallbacks and automatic Google Drive link conversion for uploads)
- Engagement metrics: reactions (configurable per hub), comment count, sentiment, utility, and engagement scores

### 3.3 Taxonomy

The platform uses a **multi-dimensional taxonomy** system to classify publications:

- **Platform dimensions** (`control_type: 'platform'`) — shared across all hubs (e.g. topic, format, region); each dimension supports an optional text `description` for curator guidance
- **Hub dimensions** (`control_type: 'hub'`) — private to a specific hub (e.g. proprietary categories)
- Each dimension has a set of **values**; AI assigns values from the existing set and may propose new ones
- **Two configurable classification thresholds** (set in `platform_settings`):
  - `taxonomy_classification_threshold` (default 95%) — applied to objective/discrete dimensions (Publication Group, Type, Category, Temporal Type, Intent Type)
  - `persona_classification_threshold` (default 75%) — applied to subjective style/persona dimensions (Sentiment, Density, Framing Mode, etc.)
- **AI proposals** are tracked and surfaced to curators for approval (hub-level tag management)
- **Hub Boards** use taxonomy dimension values as filters to create navigable sub-feeds

### 3.4 Consumer (Reader)

A **Consumer** is a reader of the client-facing app. Consumers are distinct from curator users (who authenticate via Supabase Auth).

Consumers have:
- An anonymous or named profile with a display alias
- A **subscription tier**: `guest`, `free`, or `premium`
- **Hub subscriptions** — curated hubs they follow
- **Messenger identities** — linked Telegram account for receiving push notifications
- Reactions, comments, and engagement data
- Stripe customer and subscription IDs for paid hub access

### 3.5 Deployment

A **Deployment** is a named, tokenised access grant that allows the `xentara-client` (or any third-party consumer) to access the Platform API on behalf of a specific context:

- Deployments carry an `X-Deployment-Token` header
- They support `allow-list` or `deny-list` hub access modes
- A deployment may be pinned to a **single hub** — which activates white-label mode in the client (auto-redirect, hub-scoped branding, hub-scoped PWA manifest)
- Deployments have a `hosting_type` field (`self-hosted` or `managed-vercel`) to support platform billing of managed client deployments
- The platform validates all API calls against the deployment's access rules

### 3.6 Announcements & Messages

The platform supports a targeted message and announcement system to broadcast system-wide or hub-specific notifications to readers:
- **Platform Promotions** (formerly "Platform Messages"): Created by platform administrators and broadcast across the network. Support targeting by language (`target_language`), hub selection mode (`all` / specific hubs via `target_hub_ids`), and can be configured to show only to unauthenticated visitors or authenticated users.
- **Hub Messages**: Created by hub curators to communicate updates, campaign callouts, or instructions specifically to that hub's readers (`hub_message`).
- **AdminMessage Structure**: Messages support optional titles, body descriptions (supporting markdown), and multiple call-to-action buttons/links with improved layout styling.

### 3.7 Publisher Profiles

Each Monitored Source can be linked to a **Publisher** entity — a richer profile of a media organisation or content creator. Publisher profiles are hub-scoped and include metadata such as entity type, source type, ownership type, political leaning, bias rating, factuality score, and optional logo/description fields. The curator can control which publisher metadata fields are displayed in the reader via the `publisher_fields` setting in `hub_reader_settings`.

### 3.8 Commercial Model

The platform includes a foundational commercial layer for monetising hubs and deployments:

- **Paid Hub Subscriptions** — hubs may charge reader subscription fees (`subscription_fee_cents`) backed by Stripe products and prices; a `paid_hub_subscriptions` table tracks Stripe subscription status per consumer per hub
- **Hub Billing Accounts** — each hub has a `hub_billing_accounts` record tracking its Stripe customer ID and optional Stripe Connect account for payouts
- **Hub Billing Cycles** — AI token usage is tracked per billing cycle (`hub_billing_cycles`) to support overage billing
- **Promotion Clicks / Affiliate Programme** — a `promotion_clicks` table with deduplication (SHA-256 hash) records click events for both hub and platform promotions, attributed to the referring hub and consumer (anonymous, registered-anonymous, or identified)

### 3.9 Curator Assistants

A platform-wide **Curator Assistants** directory lets hub owners hire human curators to support their operation:

- Assistants list their name, role, bio, hourly rate, and emoji avatar
- An `is_active` flag controls directory visibility
- Hubs hire assistants via `hub_hired_assistants` (with weekly hours allocation)
- Payout banking details are stored in `curator_assistant_payouts` (visible only to platform admins)
- The platform admin interface at `/dashboard/admin/curator-assistants` manages the directory

---

## 4. Platform Architecture (`xentara`)

### 4.1 Technology Stack

| Layer | Technology |
|-------|-----------|
| Framework | Next.js (App Router) |
| Database | Supabase (PostgreSQL with RLS) |
| Auth | Supabase Auth |
| Background Jobs | Inngest |
| AI (primary) | Google Gemini (`gemini-3-flash-preview`) |
| AI (fallback) | Inception Labs (`mercury-2`) |
| Messaging | Grammy (Telegram Bot API) |
| Email | Resend (transactional invitation emails) |
| Package Manager | pnpm (monorepo with `pnpm-workspace.yaml`) |
| SDK Package | `packages/api-client` (`@xentara/api-client`) |

### 4.2 Curator Dashboard (`/dashboard`)

The back-office portal for curators. Key sections:

| Route | Purpose |
|-------|---------| 
| `/dashboard` | Studio Overview — grid of all hubs with recent activity |
| `/dashboard/hubs/[slug]` | Hub Curator Portal — source registry, intelligence feed |
| `/dashboard/hubs/[slug]/intelligence` | Engagement analytics — sentiment, utility, engagement scores, reaction breakdown, comment inbox |
| `/dashboard/hubs/[slug]/profile` | Publisher profiles — entity metadata, bias ratings, factuality scores |
| `/dashboard/hubs/[slug]/promotions` | Hub promotions management |
| `/dashboard/hubs/[slug]/inbox` | Subscriber/interaction inbox |
| `/dashboard/hubs/[slug]/settings` | Full hub settings: branding, channels, taxonomy, engagement, team, reader access, filter rules |
| `/dashboard/hubs/[slug]/reader` | Advanced reader configuration (Services, Boards, Workflows, Socials) |
| `/dashboard/hubs/[slug]/onboarding` | Hub onboarding wizard for new hubs |
| `/dashboard/taxonomy` | Hub-level taxonomy management (dimensions, values, proposals) |
| `/dashboard/history` | Published article archive with search and filtering (including filter by source) |
| `/dashboard/deployments` | Deployment token management for API access grants |
| `/dashboard/admin/*` | Platform-level administration (platform taxonomy, platform promotions, ingestion failure review, reaction configuration, curator assistants directory) |

### 4.3 Intelligence Pipeline (Inngest Functions)

The pipeline is an event-driven, multi-step background system orchestrated by **Inngest**:

#### Step 1 — Discovery Agent (Instant + Recurring)
- **Instant** (`xentara/source.added`): triggered when a curator adds a source or manually refreshes it
- **Recurring** (`0 * * * *`): hourly cron that scans all active sources across all active hubs
- Respects per-source `is_ingestion_paused` flag — skips paused sources entirely (no failures logged)
- Respects per-source `filter_before_date` threshold — ignores discovered articles published before this date
- Fetches new items from YouTube channels, RSS feeds, or RSSHub-resolved feeds
- Inserts new `publications` records with status `raw`
- Dispatches `xentara/publication.detected` events (via `step.sendEvent` — memoised, no duplicates)

#### Step 2 — Intelligence Pipeline (`xentara/publication.detected`)
A single Inngest function runs the following steps sequentially for each detected publication:

1. **Content Ingestion** — fetches transcript/text from the source (YouTube captions via transcript API, RSS/RSSHub article body, or web article scraping)
2. **Single-Pass Intelligence** (AI) — one combined Gemini/Inception API call returns:
   - `summary` (Markdown)
   - `refined_title`
   - `byline` (≤150 chars)
   - `synopsis` (2–3 sentences)
   - `sentiment` score (–1.0 → 1.0)
   - `article_type` (`text`, `video`, `podcast`)
   - `original_language` (ISO language code)
   - `taxonomy_classifications` (matched dimension values)
   - `new_suggestions` (proposed new taxonomy values)
3. **Taxonomy Agent** — saves taxonomy linkages and AI proposals to the database
4. **Filter Rules Evaluation** — checks blocklist/allowlist keyword rules per source; tags content `auto_purge_tagged` if matched
5. **Finalisation** — writes all enriched data back to the publication, sets status to `ready`

**AI Fallback Chain:** Gemini (primary, up to 10 API keys with rotation + cooldown) → Inception Labs (fallback) → raw excerpt (last resort if unconfigured)

**AI Classification Thresholds:** The pipeline applies the appropriate threshold from `platform_settings` — `taxonomy_classification_threshold` for objective dimensions, `persona_classification_threshold` for style/persona dimensions.

#### Step 3 — Distribution Agent (`xentara/publication.published`)
Triggered when a curator publishes an article:
- Fetches hub channels (Telegram groups and channels)
- Fetches subscribed consumers with linked Telegram identities
- Formats content using hub-aware formatters (with thumbnail support)
- Broadcasts to Telegram channels (with native comment button for channels; inline keyboard for groups)
- Sends DMs to subscribed consumers
- Logs all delivery attempts (success / failure / rate-limited) to `distribution_log`

#### Step 4 — Promotions Agent (`0 * * * *`)
Hourly cron that checks all active hub promotions:
- Respects date windows (`start_date`, `end_date`)
- Supports one-shot or repeating frequency gates (`frequency_hours`)
- Supports article-count gates between repeats (`min_articles_between_repeats`)
- Supports `always_display_first` flag to pin a promo at the top of the reader feed
- Delivers to: Telegram channels, Telegram DMs, and/or the Reader Feed (`reader_feed_promos` table)

#### Step 5 — Engagement Feedback Agent
Processes consumer engagement events (reactions, comments) and updates scores.

#### Step 6 — Email Agent (`xentara/curator.invited`)
Triggered when a curator is invited to a hub team:
- Fetches hub context (name) for personalised content
- Sends a branded HTML invitation email via the **Resend** API
- Includes hub name, inviter email, assigned role, and a direct invitation link

### 4.4 Content Sourcing Engine

Three source type handlers live in `src/utils/sourcing/`:

| Type | Handler | Description |
|------|---------|-------------|
| `youtube` | `youtube.ts` | Fetches latest videos from a YouTube channel; retrieves captions/transcript |
| `rss` | `rss.ts` | Parses RSS/Atom feeds; pre-loads article body where available |
| `rsshub` | `rsshub.ts` | Resolves RSSHub route URLs to a self-hosted or public RSSHub instance |

### 4.5 Headless API (`/api/v1`)

A REST API consumed by the client and external deployments:

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/hubs` | List public hubs (filtered by deployment access rules) |
| `GET /api/v1/hubs/[slug]` | Hub config: branding, boards, engagement config, channels, reader settings |
| `GET /api/v1/hubs/[slug]/feed` | Paginated publication feed with board/sub-board filtering |
| `GET /api/v1/hubs/[slug]/feed/[articleId]` | Single article with full metrics |
| `GET /api/v1/hubs/[slug]/feed/[articleId]/metadata` | Decoupled article metadata (AI scores, classifications) for subscriber-only access |
| `GET /api/v1/hubs/[slug]/publishers/counts` | Retrieve article counts for hub publishers |
| `GET /api/v1/hubs/[slug]/config` | Hub configuration (reader settings, services) |
| `GET /api/v1/hubs/[slug]/subscribe` | Consumer subscription management |
| `GET /api/v1/hubs/[slug]/subscribers` | Subscriber count |
| `GET /api/v1/hubs/[slug]/suggestions` | Hub content suggestions (submit/list) |
| `POST /api/v1/hubs/[slug]/placements` | Content placement requests |
| `GET /api/v1/deployments/config` | Deployment configuration |
| `GET /api/v1/deployments/validate` | Deployment token validation |
| `GET/POST /api/v1/consumers` | Consumer profile management |
| `GET/POST /api/v1/identity/[id]` | Messenger identity management |
| `POST /api/v1/identity/claim` | Claim consumer identity via token |
| `POST /api/v1/identity/link` | Link messenger identity |
| `GET /api/v1/promotions/click` | Record a promotion click (affiliate tracking) |
| `GET /api/v1/promotions/impression` | Record a promotion impression |
| `GET /api/v1/webhooks/*` | Telegram webhook handler |

All endpoints respect `X-Deployment-Token` for access control.

### 4.6 Database Schema (Supabase)

Key tables (72 migrations to date):

| Table | Purpose |
|-------|---------|
| `hubs` | Hub definitions, branding, hero media config, settings |
| `hub_memberships` | RBAC: owner/editor/viewer roles per hub |
| `hub_invitations` | Pending email invitations |
| `monitored_sources` | Feed/channel sources per hub (with `default_article_type`, `is_ingestion_paused`, `filter_before_date`) |
| `publications` | All tracked and curated articles (with `article_type`, `original_language`) |
| `publication_taxonomy_values` | Publication ↔ taxonomy value links |
| `taxonomy_dimensions` | Platform and hub taxonomy dimensions (with optional `description`) |
| `taxonomy_dimension_values` | Allowed values per dimension |
| `taxonomy_dimension_value_translations` | Localised value names |
| `taxonomy_ai_proposals` | AI-suggested new taxonomy values |
| `hub_dimension_preferences` | Hub opt-outs from platform taxonomy values |
| `hub_channels` | Distribution channels (Telegram groups/channels) |
| `hub_subscriptions` | Consumer ↔ hub subscription |
| `hub_promotions` | Hub-level promotional content (with `always_display_first`) |
| `hub_engagement_config` | Reaction types and comment enablement |
| `hub_reader_settings` | Reader access control, card layout, services config, `publisher_fields`, `listing_status`, subscription fee, Stripe IDs |
| `hub_tags` / `publication_hub_tags` | Curated tag taxonomy (flavors) |
| `hub_content_suggestions` | Reader/visitor source and content suggestions |
| `hub_placement_requests` | Third-party placement requests for hub content slots |
| `hub_billing_accounts` | Hub-level Stripe customer and Connect account for payouts |
| `hub_billing_cycles` | AI token usage tracking per billing cycle |
| `paid_hub_subscriptions` | Reader Stripe subscription status per hub |
| `consumers` | Reader/consumer profiles (with Stripe customer/subscription IDs) |
| `messenger_identities` | Linked Telegram/WhatsApp identities |
| `publication_engagements` | Reactions and comments per article |
| `distribution_log` | Delivery tracking per publication/channel |
| `promo_distribution_log` | Delivery tracking per promotion |
| `reader_feed_promos` | Active promotions in the reader feed |
| `promotion_clicks` | Promotion click events for affiliate tracking (deduplicated) |
| `boards` | Named board configurations |
| `color_palettes` | Shared, reusable custom colour palettes |
| `publishers` | Publisher entity profiles (media metadata), hub-scoped |
| `platform_settings` | Global platform configuration (incl. dual classification thresholds) |
| `platform_promotions` | Platform-wide announcements (with language and hub targeting) |
| `deployments` | API access grants (deployment tokens, `hosting_type`) |
| `deployment_hub_access` | Hub access rules per deployment |
| `source_filter_rules` | Blocklist/allowlist keyword rules per source |
| `ingestion_failures` | Failed discovery entries for curator review |
| `curator_assistants` | Platform directory of available human curation assistants |
| `hub_hired_assistants` | Hub ↔ curator assistant hiring relationships |
| `curator_assistant_payouts` | Assistant bank/payout details (admin-only) |

---

## 5. Reader Client Architecture (`xentara-client`)

### 5.1 Design Principle: White-Label Shell

The reader client is designed as a **brand-agnostic shell** that wraps the platform API. Its identity is entirely driven by runtime configuration.

By default, the platform runs on the following domain structure:
- **`xentara.buzz`**: The main reader client site (in Multi-hub discovery mode or dynamically routed based on domains).
- **`studio.xentara.buzz`**: The studio / curator dashboard where curators manage hubs, sources, and publications.

In addition, the reader client supports:
- **Multi-hub mode** (default): shows a hub discovery directory and individual hub feeds — branded as the platform (e.g. "Xentara")
- **Single-hub mode**: activated by `XENTARA_HUB_SLUG` or a `XENTARA_DEPLOYMENT_TOKEN` pointing to a `single_hub_id` — the entire app adapts to that hub's branding, with auto-redirect to the hub feed

In single-hub mode, the client:
- Uses the hub's name, logo, description for metadata/SEO
- Applies the hub's colour palette as CSS variables throughout the app
- Sets the hub's accent colour as the PWA `theme_color`
- Generates a hub-scoped PWA manifest (`start_url: /v/<slug>`)
- Shows the hub's `PoweredByXentara` badge (for attribution)

### 5.2 Technology Stack

| Layer | Technology |
|-------|-----------|
| Framework | Next.js (App Router) |
| Auth | Supabase Auth (consumers) |
| SDK | `@xentara/api-client` (git submodule from platform) |
| PWA | Web App Manifest + service worker registration |

### 5.3 Application Routes

| Route | Purpose |
|-------|---------| 
| `/` | Hub discovery directory (or auto-redirect in single-hub mode) |
| `/v/[slug]` | Hub feed page with boards, banner header, and article cards |
| `/v/[slug]/article/[id]` | Full article detail view |
| `/v/[slug]/publisher/[publisherId]` | Dedicated publisher profile page and feed |
| `/profile` | Consumer profile management |
| `/profile/link` | Messenger identity linking flow |
| `/subscriptions` | Subscription management |
| `/auth/*` | Authentication flows (including callback, confirm, reset-password) |

### 5.4 Key Components

| Component | Role |
|-----------|------|
| `HubBannerHeader` | Hero section with hub name, media (image/video/slideshow), subscriber count, board navigation |
| `HeroVideo` | Looping muted hero video with `IntersectionObserver`-based play/pause and `prefers-reduced-motion` support |
| `HeroSlideshow` | Crossfading image slideshow with configurable interval/transition, `IntersectionObserver` pause, and `prefers-reduced-motion` fallback |
| `BoardFeed` | Infinite-scroll feed with board/sub-board filtering, source filtering, sort control, and client-side pagination |
| `BoardSelector` | Sidebar board navigation (desktop) |
| `BoardSelectorDrawer` | Swipe drawer board navigation (mobile) |
| `FeedSwipeWrapper` | Mobile swipe gesture handler for board navigation |
| `FeedCard` | Standard article card in the feed (with article type badge and language flag) |
| `PromoCard` | Promotional content card in the feed |
| `ArticleDetailView` | Full article reader with summary, curator take, reactions, comments |
| `PublisherProfileDrawer` | Publisher entity profile overlay (supporting entity type/source type badge translation) |
| `PublisherProfilePage` | Full publisher profile page with feed and article count |
| `PublisherCard` | Publisher summary card with logo, description preview, and article count — used in publisher grids |
| `PublisherGrid` | Responsive grid layout of `PublisherCard` items |
| `SourceSelector` | Multi-select dropdown for filtering the feed by monitored source(s); persisted in `BoardContext` |
| `ServicesMenuDrawer` | Hub services, social links, external pages |
| `ShareModal` | Hub sharing modal with note composer, social platform grid (Telegram, WhatsApp, Facebook, LinkedIn, Signal, Discord, Instagram, Skool, Substack, Matrix/Element), clipboard copy, and native Web Share API support |
| `HubList` | Hub grid/list for the discovery directory (respects `listing_status`) |
| `Nav` | Top navigation bar (auth state, subscriptions, profile) |
| `ThemeStyle` | Injects hub palette as CSS custom properties |
| `PoweredByXentara` | Attribution badge (visible in white-label deployments) |
| `PWARegistry` | Service worker registration for installability |
| `TierGate` | Premium content access gate |
| `TierBadge` | Small inline badge (`free` / `premium`) shown on consumer profiles |
| `UpgradePrompt` | Modal prompt shown when a consumer tries to access a premium-gated feature |
| `SidebarAnnouncements` | Consolidates prioritized platform and hub announcements |
| `AdminMessageCard` | Renders announcements with body markdown and multiple action buttons |
| `SignupCallout` | High-visibility aggressive sign-up callout for comments / premium features |
| `Markdown` | Lightweight Markdown renderer used throughout the reader |

### 5.5 Theming System

The hub's colour palette is serialised as a `ThemeColorPalette` JSON object by the platform API and consumed by the client. The `ThemeStyle` component injects it as CSS custom properties (`--bg-primary`, `--accent`, etc.) at the root level, enabling full visual theming without any rebuild step.

Two palette representations are provided by the API:
- **Structured** (`color_palette`): full `theme.colors.*` object — used for advanced styling
- **Flat** (`palette`): simplified key-value object (`bg`, `accent`, `text`, etc.) — used for quick CSS var injection

### 5.6 Board State & Feed Filtering

The `BoardContext` (provided by `BoardProvider`) manages all reader-side feed state in a single shared context, persisted to `localStorage` per hub:

- `activeBoardId` / `activeSubBoardId` — active board and sub-board selection
- `selectedSources` — multi-select source filter (array of monitored source IDs)
- `sort` — feed sort order (`newest` | `oldest`)
- `totalItems` — total item count for the current board/filter combination
- `resetAll()` — clears all state and removes the persisted preference key

The `SourceSelector` component reads available sources from `HubConfigProvider` and writes selections back into `BoardContext`, which `BoardFeed` passes through to the API feed query.

---

## 6. The SDK: `@xentara/api-client`

Located at `packages/api-client` in the platform monorepo and consumed by the client via a git submodule (`xentara-submodule`).

The SDK is framework-agnostic TypeScript. Key modules:

| Module | Functions |
|--------|-----------|
| `hubs.ts` | `getPublicHubs`, `getHubBySlug`, `getHubConfig`, `getSubscriberCount`, `getDeploymentConfig` |
| `feed.ts` | `getBoardFeed`, `getSingleArticle` |
| `consumers.ts` | `getMyProfile`, `createConsumer`, `updateProfile` |
| `subscriptions.ts` | `getMySubscriptions`, `subscribeToHub`, `unsubscribeFromHub` |
| `identity.ts` | `getMyIdentities`, `linkMessengerIdentity`, `unlinkMessengerIdentity` |
| `interactions.ts` | `reactToArticle`, `commentOnArticle`, `getArticleMetrics` |

All functions accept an `apiBase` URL string, enabling the client to point to any hosted platform instance.

---

## 7. Engagement System

Publications can receive consumer engagement via:

- **Reactions**: emoji-based, configurable per hub (enabled set defined in `hub_engagement_config`). Each reaction type maps to a platform-defined label and emoji.
- **Comments**: free-text responses. Optionally AI-scored for sentiment via Gemini.
- **Scores**: three computed scores per publication:
  - `score_sentiment`: aggregate reaction sentiment
  - `score_utility`: informational value signal
  - `score_engagement`: overall interaction intensity
- Scores are computed by a Supabase RPC (`compute_publication_scores`) and displayed in the curator Intelligence dashboard.

---

## 8. Distribution System

When a curator publishes an article, the Distribution Agent delivers it to:

1. **Telegram Channels** (`-100...` IDs): posted with photo (thumbnail) or text, HTML parse mode. Channel posts omit inline keyboard so Telegram's native comment button appears.
2. **Telegram Groups**: posted with photo or text and inline keyboard (reaction buttons + source link).
3. **Subscriber DMs**: individual Telegram DMs to all consumers who have subscribed to the hub and linked their Telegram account.

Promotions follow the same distribution matrix (channels + DMs + reader feed) but are managed separately with time-window and frequency gates.

---

## 9. Publisher Profiles

Each Monitored Source can be linked to a **Publisher** entity — a richer profile of a media organisation or content creator. Publisher profiles include:

- **Entity Types**: individual, media establishment, PR function, agency, NGO/civil society (`ngo_civil_society`)
- **Source Types**: social media, community hubs, content creators, corporate official, academic research, civil society advocacy (`civil_society_advocacy`)
- **Ownership Types**: independent, individual, telecom, government, private equity, corporation, association_pr, media_conglomerate, other
- **Editorial Metadata**: political leaning, bias rating, factuality score (`very_low` → `very_high`)
- **Location & Reach**: optional contact, domicile information, and total article count integration
- **Branding**: support for logos and textareas for metadata descriptions (with flexible, relaxed validation limits)
- **Multilingual Support**: localized translations for entity and source type badges (supporting English, Afrikaans, and others)
- **Reader Display Control**: curators can configure which publisher metadata fields are shown to readers via the `publisher_fields` setting in hub reader settings

Publisher profiles are accessible in the reader via the `PublisherProfileDrawer` overlay and dedicated publisher profile/feed pages.

---

## 10. Deployment Model

```
┌─────────────────────────────────────────────────────────────────────┐
│                     Platform (xentara)                              │
│                                                                     │
│  ┌──────────────┐   ┌──────────────────┐   ┌────────────────────┐  │
│  │  Curator     │   │  AI Pipeline     │   │  Headless API      │  │
│  │  Dashboard   │   │  (Inngest)       │   │  /api/v1           │  │
│  └──────────────┘   └──────────────────┘   └────────┬───────────┘  │
│                                                      │              │
│  ┌──────────────────────────────────────────────────┴───────────┐  │
│  │                    Supabase (PostgreSQL + Auth)               │  │
│  └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                      X-Deployment-Token (API)
                                   │
          ┌────────────────────────┴─────────────────────────┐
          │                                                   │
   ┌──────┴──────────────┐                       ┌───────────┴───────┐
   │  xentara-client     │                       │  Third-party      │
   │  (PWA Reader)       │                       │  Client           │
   │                     │                       │                   │
   │  Multi-hub mode:    │                       │  Uses             │
   │  Hub directory      │                       │  @xentara/        │
   │                     │                       │  api-client SDK   │
   │  Single-hub mode:   │                       └───────────────────┘
   │  Full white-label   │
   │  branded PWA        │
   └─────────────────────┘
```

---

## 11. Content Flow Summary

```
Source (YouTube / RSS / RSSHub)
        │
        ▼ (hourly cron or manual trigger)
  Discovery Agent
        │  [skips paused sources; filters by filter_before_date]
        │  xentara/source.added
        ▼
  Publication record created (status: raw)
        │  xentara/publication.detected
        ▼
  Intelligence Pipeline
    ├── Content Ingestion     (status: transcribing)
    ├── Single-Pass AI        (status: analyzing)
    │     ├── summary
    │     ├── byline / synopsis / sentiment
    │     ├── article_type / original_language
    │     └── taxonomy classifications (dual thresholds)
    ├── Taxonomy Agent        (saves tags & proposals)
    ├── Filter Rules Check    (blocklist / allowlist)
    └── Finalise              (status: ready)
                │
                ▼ (curator reviews in dashboard)
          Curator publishes
                │  xentara/publication.published
                ▼
        Distribution Agent
          ├── Telegram channels (broadcast)
          ├── Telegram groups   (broadcast)
          └── Subscriber DMs
                │
                ▼
        Readers see it in xentara-client feed
```

---

## 12. File Structure Reference

### `xentara` (Platform)

```
src/
├── app/
│   ├── api/v1/              # Headless REST API
│   │   ├── hubs/            # Hub feed, config, publishers, suggestions, placements
│   │   ├── consumers/       # Consumer profile
│   │   ├── deployments/     # Deployment token validation/config
│   │   ├── identity/        # Messenger identity management
│   │   ├── promotions/      # Promotion click & impression tracking
│   │   └── webhooks/        # Telegram webhook handler
│   ├── dashboard/           # Curator back-office
│   │   ├── admin/           # Platform administration
│   │   │   ├── curator-assistants/  # Curator assistants directory
│   │   │   ├── ingestion-failures/
│   │   │   ├── platform-promotions/
│   │   │   ├── platform-taxonomy/
│   │   │   └── reaction-config/
│   │   ├── deployments/     # API token management
│   │   ├── hubs/[slug]/     # Per-hub management
│   │   │   ├── inbox/
│   │   │   ├── intelligence/
│   │   │   ├── onboarding/
│   │   │   ├── profile/
│   │   │   ├── promotions/
│   │   │   ├── reader/
│   │   │   └── settings/
│   │   ├── history/
│   │   └── taxonomy/
│   └── p/[id]/              # Publication permalink viewer
├── inngest/                 # Background job functions
│   ├── functions.ts         # Discovery + Intelligence pipeline
│   ├── distribution.ts      # Publication distribution
│   ├── promotions.ts        # Promotions distribution
│   ├── engagement.ts        # Engagement feedback
│   └── emails.ts            # Transactional email (Resend)
├── lib/
│   ├── Telegram/            # Telegram formatting
│   └── engagement/          # Reaction config
├── utils/
│   ├── ai/engine.ts         # Gemini + Inception AI engine
│   ├── sourcing/            # YouTube, RSS, RSSHub handlers
│   └── supabase/            # DB client utilities
packages/
└── api-client/src/          # @xentara/api-client SDK
supabase/migrations/         # 72 database migrations
```

### `xentara-client` (Reader)

```
src/
├── app/
│   ├── v/[slug]/                  # Hub feed pages
│   │   ├── article/[id]/          # Article detail page
│   │   └── publisher/[publisherId]/ # Dedicated publisher profile page
│   ├── profile/                   # Consumer profile
│   │   └── link/                  # Messenger identity linking
│   ├── subscriptions/             # Subscription management
│   └── auth/                      # Authentication (callback, confirm, reset-password)
├── components/                    # All UI components
│   ├── HeroSlideshow.tsx          # Crossfading image slideshow
│   ├── HeroVideo.tsx              # Looping hero video
│   ├── PublisherCard.tsx          # Publisher summary card
│   ├── PublisherGrid.tsx          # Publisher card grid
│   ├── ShareModal.tsx             # Hub social sharing modal
│   ├── SourceSelector.tsx         # Feed source filter dropdown
│   ├── TierBadge.tsx              # Consumer tier badge
│   └── UpgradePrompt.tsx          # Premium upgrade modal
├── contexts/
│   ├── AuthProvider.tsx           # Consumer auth context
│   ├── BoardContext.tsx           # Board/source/sort state (localStorage-persisted)
│   └── HubConfigProvider.tsx      # Hub branding context
├── hooks/
│   ├── useAuth.ts                 # Auth hook
│   └── useTranslation.ts          # i18n translation hook
├── locales/                       # i18n strings
└── utils/                         # Utility functions (deployment, URL helpers)
xentara-submodule/                 # Git submodule → @xentara/api-client
```

---

*Last updated: July 2026*
