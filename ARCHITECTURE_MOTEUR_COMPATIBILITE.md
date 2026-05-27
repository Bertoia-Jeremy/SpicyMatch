# Architecture du moteur de compatibilité aromatique SpicyMatch

> **Document de référence**. Destiné à être conservé à la racine du projet pour reprise ultérieure sans contexte conversationnel.

> **Doctrine** : Deux algorithmes synergiques, déterministes, complexité $\mathcal{O}(N)$ ou indexable BDD. Refus explicite de l'over-engineering. Latence cible < 100 ms pour un catalogue de 100 épices.

---

## 1. Contexte & Objectif métier

### 1.1 Définition du problème

```
GET /api/match?spices=id_1,id_2,…,id_k&limit=20
       ↓
Mortier M = {s_1, …, s_k}, k ∈ [1, 10]
       ↓
Moteur (2 algorithmes en cascade)
       ↓
Réponse JSON: [{ candidate_id, name, score, breakdown }] trié par score décroissant
```

### 1.2 Hypothèse fondatrice

*Two ingredients are likely to taste good together if they share many flavor compounds* (Ahn, Y.-Y. et al., 2011, *Flavor network and the principles of food pairing*, Scientific Reports 1:196).

Étendue ici par la **prise en compte de la perception humaine** via les seuils olfactifs (van Gemert, 2011) : seuls les composés effectivement perceptibles (OAV > 1) entrent dans le calcul.

### 1.3 Doctrine — Duo déterministe

| # | Rôle | Algorithme | Complexité |
|---|---|---|---|
| **1** | **Le Veto** — filtrage strict | Graphe biparti booléen sur composés OAV-actifs | SQL indexé, $\mathcal{O}(N \cdot k \cdot \log N)$ |
| **2** | **Le Score** — mesure d'alchimie | Tanimoto pondéré OAV | $\mathcal{O}(\|C^*\|)$ par candidat |

**Tout le reste est exclu** : pas de Tversky, pas de Cosinus, pas de Wasserstein, pas de fingerprints, pas de KL/JS, pas de PageRank, pas de Louvain, pas de PMI/NPMI, pas de ML.

---

## 2. Algorithme 1 — Le Veto (graphe biparti booléen)

### 2.1 Principe

Le mortier $M$ et chaque candidat $c$ partagent un sous-ensemble de composés **OAV-actifs** (perceptibles). La règle du veto rejette tout candidat ne partageant pas un nombre minimal $\tau$ de composés OAV-actifs **avec chaque épice du mortier**.

### 2.2 Formalisation

Soit $\Pi_s^* = \{ i \in C \mid OAV_i^s > 1 \}$ l'ensemble des composés OAV-actifs de l'épice $s$. Le candidat $c$ **passe** le veto ssi :

$$\forall s_i \in M : |\Pi_c^* \cap \Pi_{s_i}^*| \geq \tau$$

**Paramètre** : $\tau = 1$ (un seul pont aromatique perceptible suffit par épice du mortier). Ajustable empiriquement.

### 2.3 Implémentation SQL (MariaDB / Doctrine DBAL)

Pré-requis : table `spice_active_compound` matérialisée par job Messenger (cf. §5).

```sql
SELECT c.id
FROM spices c
WHERE c.id NOT IN (:mortar_ids)
  AND (
    SELECT COUNT(DISTINCT m.spice_id)
    FROM spice_active_compound m
    WHERE m.spice_id IN (:mortar_ids)
      AND EXISTS (
        SELECT 1
        FROM spice_active_compound sc
        WHERE sc.spice_id = c.id
          AND sc.aromatic_compound_id = m.aromatic_compound_id
      )
  ) = :mortar_size;
```

Sémantique : le candidat $c$ passe si **chaque** épice du mortier (au nombre de `mortar_size`) partage au moins un composé OAV-actif avec lui.

**Index requis** : `INDEX (spice_id, aromatic_compound_id)` et `INDEX (aromatic_compound_id, spice_id)` sur `spice_active_compound`.

### 2.4 Complexité

- À 100 candidats × 3 épices du mortier × 200 composés : **< 5 ms** en pratique avec les bons index.
- Élimine typiquement **60 à 80 %** des candidats avant tout calcul de score.

### 2.5 Dégradation gracieuse

Tant que les concentrations et ODT ne sont pas saisis, fallback sur le veto **présence-uniquement** (donnée actuellement disponible dans `spices_aromatics_compounds`).

```sql
-- Variante fallback sans OAV (donnée actuelle, sans concentration)
SELECT c.id
FROM spices c
WHERE c.id NOT IN (:mortar_ids)
  AND (
    SELECT COUNT(DISTINCT sc.spice_id)
    FROM spices_aromatics_compounds sc
    WHERE sc.spice_id IN (:mortar_ids)
      AND EXISTS (
        SELECT 1 FROM spices_aromatics_compounds sc2
        WHERE sc2.spice_id = c.id
          AND sc2.aromatic_compound_id = sc.aromatic_compound_id
      )
  ) = :mortar_size;
```

---

## 2.bis Extensions multi-matricielles & physico-chimiques (Étape 3)

Le pipeline a été étendu pour intégrer le contexte culinaire :

### 2bis.1 Matrices ODT multiples (Étape 1+2)

L'enum `OdtMatrix` (`AIR | WATER | OIL`) sélectionne la valeur ODT appropriée
pour chaque composé. La shadow table `spice_active_compound` matérialise
les OAV par triplet `(spice_id, compound_id, matrix)` — rebuild atomique
en 3 passes INSERT dans une transaction InnoDB unique, suivie d'un RENAME TABLE.

### 2bis.2 Partition de Nernst (Étape 3A+3B+3C)

Pour un contexte culinaire `(fatRatio, waterRatio)`, la concentration efficace
dans la phase ciblée suit l'équilibre Nernst :

$$C_{water} = \frac{C_{total}}{K_{ow} \cdot \varphi_{oil} + \varphi_{water}}, \quad C_{oil} = K_{ow} \cdot C_{water}$$

où $K_{ow} = 10^{\log P}$ est le coefficient de partage octanol/eau du composé
(persisté dans `compound_physical`).

### 2bis.3 Décroissance temporelle

Pour une cuisson $(T_{°C}, t_{min})$, modèle de premier ordre simplifié :

$$k(T) = K_{boiling} \cdot \frac{T - T_{inert}}{bp - T_{inert}} \quad \text{(saturé à 1 si } T \geq bp\text{)}, \quad C(t) = C_0 \cdot e^{-k(T) \cdot t}$$

Avec $K_{boiling} = 0.1 / \text{min}$ et $T_{inert} = 50\,°C$ (calibration empirique).

### 2bis.4 Cinétique aromatique (AromaKinetics)

Classification HEAD/HEART/BASE dérivée du point d'ébullition :
- HEAD : $bp < 150\,°C$ — notes volatiles, à ajouter en fin de cuisson
- HEART : $150 \leq bp \leq 250\,°C$ — notes structurantes
- BASE : $bp > 250\,°C$ — notes lourdes, résistent à la cuisson longue

### 2bis.5 Limitations actuelles & assumptions

- **Correction Nernst scalaire** : appliquée indépendamment par composé. Ne tient
  pas compte des interactions cross-compounds (compétition, micelles, encapsulation).
- **Modèle de décroissance empirique** : linéaire entre $T_{inert}$ et $bp$, saturé
  au-delà. Approximation Arrhenius simplifiée. Constantes (`K_AT_BOILING`, `T_INERT`)
  calibrées sur des observations qualitatives — à valider expérimentalement.
- **Single-tenant** : la shadow table `spice_active_compound` est globale ; le
  rebuild ne tient pas compte de multi-utilisateurs / multi-bibliothèques.
- **Domaine de validité** : matrices aqueuses/huileuses, cuisson conventionnelle
  à pression atmosphérique. Non applicable au four sec haute température (>200 °C
  convection), à la lyophilisation, ou aux extraits supercritiques.

---

## 3. Algorithme 2 — Le Score (Tanimoto pondéré OAV, log-compressé)

> **Note compression perceptuelle** : les OAV bruts s'étalent sur ~6 ordres de
> grandeur (1 → 10⁸). En Tanimoto linéaire, le composé le plus actif écrase
> numériquement les autres → la majorité des candidats compatibles (passant le
> veto) scorent 0 %. La perception olfactive étant logarithmique (Weber-Fechner /
> Stevens), le poids utilisé est `w_i = OAV_i > 1 ? ln(OAV_i) : 0`. Le clamp à 0
> sous OAV=1 reflète le seuil de détection (van Gemert) et gère les OAV < 1 issus
> de la correction Nernst (Étape 3C). Le choix de la base est neutre (se simplifie
> dans le ratio Σmin/Σmax).

### 3.1 Principe

Pour chaque candidat $c$ ayant passé le veto, calcule une similarité de Tanimoto pondérée par les valeurs d'activité odorante (OAV), entre le profil OAV du candidat et le **profil OAV agrégé du mortier**.

### 3.2 Profil OAV agrégé du mortier

Pour chaque molécule $i$ :

$$OAV_i^M = \max_{s \in M} OAV_i^s$$

> Le **max** est préféré à la moyenne : la note dominante d'un mortier détermine sa signature, pas la moyenne arithmétique des contributions.

### 3.3 Formule du score

$$\boxed{S_{OAV}(c, M) = \frac{\displaystyle\sum_{i \in C} \min\left(OAV_i^c, OAV_i^M\right)}{\displaystyle\sum_{i \in C} \max\left(OAV_i^c, OAV_i^M\right)}}$$

avec $S_{OAV} \in [0, 1]$. Score affiché : $\alpha = \lfloor 100 \cdot S_{OAV} \rfloor$.

### 3.4 Implémentation PHP

```php
namespace App\Service\Match;

final class OavTanimotoScorer
{
    /**
     * @param array<int, float> $candidateOav   compound_id => OAV
     * @param array<int, float> $mortarOav      compound_id => OAV
     */
    public function score(array $candidateOav, array $mortarOav): float
    {
        $compoundIds = array_unique([
            ...array_keys($candidateOav),
            ...array_keys($mortarOav),
        ]);

        $minSum = 0.0;
        $maxSum = 0.0;

        foreach ($compoundIds as $id) {
            $a = $candidateOav[$id] ?? 0.0;
            $b = $mortarOav[$id] ?? 0.0;
            $minSum += min($a, $b);
            $maxSum += max($a, $b);
        }

        return $maxSum > 0.0 ? $minSum / $maxSum : 0.0;
    }
}
```

### 3.5 Complexité

- Calcul d'un score : $\mathcal{O}(|C|)$ avec $|C| \approx 200$ composés (worst case FlavorDB).
- Sur 100 candidats survivants : $\mathcal{O}(N \cdot |C|) \approx 20\,000$ ops PHP. **< 20 ms**.

### 3.6 Propriétés

- ✅ Borné [0, 1], symétrique, monotone.
- ✅ Fusionne **chimie** (composés partagés) + **perception humaine** (ODT) + **quantité** (concentration). Suffit seul.
- ⚠️ Dépend de la qualité des données ODT et concentration (cf. §6 acquisition).

---

## 4. Pipeline de calcul — Flux séquentiel d'une requête API

### 4.1 Endpoint

```
GET /api/match?spices=id_1,id_2,…,id_k
              &limit=20
              &matrix=air|water|oil                   (défaut: air)
              &fat=0.0..1.0                           (défaut: 0)
              &water=0.0..1.0                         (défaut: 1-fat)
              &cooking_time=0..1440                   (minutes, défaut: 0)
              &temperature=-50..500                   (°C, défaut: 20)

Réponse 200 OK :
{
  "mortar": [1, 2],
  "results": [
    { "id": 14, "name": "Marjolaine", "score": 87 },
    { "id": 22, "name": "Cumin",      "score": 76 },
    ...
  ],
  "oav_mode": true,
  "matrix": "air",
  "fat_ratio": 0.0,
  "water_ratio": 1.0,
  "cooking_time_min": 0,
  "temperature_celsius": 20,
  "count": 2
}
```

Tous les paramètres culinaires sont validés explicitement (is_numeric + is_finite + bornes
via `CulinaryContext::FAT_RATIO_MIN/MAX` etc.) — toute valeur invalide retourne 400.

### 4.2 Diagramme de séquence

```
Client            MatchController     MortarProfileBuilder    CandidateVetoRepository    OavTanimotoScorer
  │                     │                       │                        │                        │
  │ GET /api/match      │                       │                        │                        │
  ├────────────────────▶│                       │                        │                        │
  │                     │  1. validate          │                        │                        │
  │                     │                       │                        │                        │
  │                     │  2. build Π_M*        │                        │                        │
  │                     ├──────────────────────▶│                        │                        │
  │                     │◀── Π_M* (cached) ─────│                        │                        │
  │                     │                       │                        │                        │
  │                     │  3. veto SQL          │                        │                        │
  │                     ├───────────────────────────────────────────────▶│                        │
  │                     │◀── survivor IDs ──────────────────────────────│                        │
  │                     │                       │                        │                        │
  │                     │  4. batch-load survivor OAV profiles           │                        │
  │                     ├───────────────────────────────────────────────▶│                        │
  │                     │◀── profiles ──────────────────────────────────│                        │
  │                     │                       │                        │                        │
  │                     │  5. score each survivor                        │                        │
  │                     ├───────────────────────────────────────────────────────────────────────▶│
  │                     │◀── [(c, S_OAV)] ──────────────────────────────────────────────────────│
  │                     │                       │                        │                        │
  │                     │  6. sort desc + slice limit                    │                        │
  │                     │                       │                        │                        │
  │ 200 OK              │                       │                        │                        │
  │◀────────────────────│                       │                        │                        │
```

### 4.3 Détail des 7 étapes

| # | Étape | Composant | Complexité | Budget latence |
|---|---|---|---|---|
| 1 | Validation des IDs ($k \in [1, 10]$) + ctx culinaire | `MatchController` + `MortarIds` + `CulinaryContext` | $\mathcal{O}(k)$ | < 1 ms |
| 2 | Construction profil OAV agrégé $\Pi_M^*$ par matrice | `MortarProfileBuilder` + Symfony Cache (cache par matrice) | Cache hit : $\mathcal{O}(1)$ ; miss : $\mathcal{O}(k \cdot \|C^*\|)$ | 0–10 ms |
| 3 | Veto SQL biparti par matrice | `CandidateVetoRepository::findSurvivors($mortar, $matrix)` | $\mathcal{O}(N \cdot k \cdot \log N)$ | < 5 ms |
| 4 | Hydratation OAV des survivants (1 SELECT IN filtré matrice) | `SpiceActiveCompoundRepository::loadOavProfilesBatch()` | $\mathcal{O}(N' \cdot \|C^*\|)$ | 5–15 ms |
| **5** | **Correction physico-chimique (Nernst × decay)** — skip si ctx neutre | `OavPartitionCalculator` + `CompoundPhysicalRepository::loadByCompoundIds()` | $\mathcal{O}(\|C^*\|)$ batch + $\mathcal{O}(N' \cdot \|C^*\|)$ applique | 0–10 ms |
| 6 | Scoring Tanimoto OAV × N' (sur profils corrigés) | `OavTanimotoScorer::score()` | $\mathcal{O}(N' \cdot \|C^*\|)$ | < 20 ms |
| 7 | Tri descendant + slicing `limit` | PHP natif `usort` | $\mathcal{O}(N' \log N')$ | < 1 ms |

**Latence cumulée cible** : **p50 < 50 ms, p99 < 100 ms** pour $N = 100$, $k \leq 5$, ctx neutre.
Avec ctx étendu (correction Nernst+decay activée) : ajouter ~5–10 ms (1 batch query CompoundPhysical + N applications scalaires).

### 4.4 Stratégie de cache

Trois pools cache distincts :

| Pool | Clé | TTL | Invalidation |
|---|---|---|---|
| `match.mortar_profile.cache` | `match.mortar.{matrix}.{sorted_ids}` | air 24h / water+oil 1h / vide 5min (sentinel) | event-driven via `SpiceConcentrationChangedListener` sur `SpiceCompoundConcentration` ou `CompoundOdt` |
| `match.insights.cache` | `match.insights.compare.{mortar_hash}.{ctx_hash}.l{limit}` <br> `match.insights.timeline.{compound_ids_hash}.{ctx_hash}` | 1h | TTL only (dépend indirectement de `match.mortar_profile.cache` via la pipeline) |
| `spice.compatibility.cache` | top-pairs / top-triplets précalculés | 1h | TTL only |

- **Cache niveau 1** : `MortarProfileBuilder` matérialise $\Pi_M^*$ par matrice + sentinel `[]` pour court-circuiter les "absence de données".
- **Cache niveau 2** (Étape 3E-3) : `MatrixComparator` (3× pipeline en parallèle conceptuel) et `CookingTimelineBuilder` (classification cinétique + rétention). Clés via `CulinaryContext::signatureHash()` (source unique).
- **Cache niveau 3** (future) : réponse JSON complète à activer si charge le justifie.

### 4.5 Asynchronisme

Le pipeline runtime est **synchrone** (latence critique). L'asynchrone Symfony Messenger sert uniquement aux **précalculs offline** :
- `RecomputeOavTableMessage` : matérialise `spice_active_compound` à partir de `spice_compound_concentration` × `compound_odt`. Déclenché par hook Doctrine.

---

## 5. Schéma BDD

Trois nouvelles tables. Aucune autre extension.

```sql
-- 5.1 Seuil olfactif par molécule (constante chimique référencée)
CREATE TABLE compound_odt (
  aromatic_compound_id INT NOT NULL PRIMARY KEY,
  odt_ppm DECIMAL(14, 8) NOT NULL,
  matrix ENUM('water', 'oil', 'air') NOT NULL DEFAULT 'air',
  reference_source VARCHAR(255) NOT NULL,
  imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aromatic_compound_id) REFERENCES aromatic_compound(id)
);

-- 5.2 Concentration d'une molécule dans une épice
CREATE TABLE spice_compound_concentration (
  spice_id INT NOT NULL,
  aromatic_compound_id INT NOT NULL,
  concentration_ppm DECIMAL(14, 4) NOT NULL,
  source VARCHAR(255) NOT NULL,
  imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (spice_id, aromatic_compound_id),
  INDEX idx_compound (aromatic_compound_id),
  FOREIGN KEY (spice_id) REFERENCES spices(id),
  FOREIGN KEY (aromatic_compound_id) REFERENCES aromatic_compound(id)
);

-- 5.3 Vue matérialisée des composés OAV-actifs (rebuild via Messenger)
CREATE TABLE spice_active_compound (
  spice_id INT NOT NULL,
  aromatic_compound_id INT NOT NULL,
  oav_value DECIMAL(12, 4) NOT NULL,
  PRIMARY KEY (spice_id, aromatic_compound_id),
  INDEX idx_compound_spice (aromatic_compound_id, spice_id),
  CHECK (oav_value > 1)
);
```

Le rebuild de `spice_active_compound` se fait via :

```sql
TRUNCATE spice_active_compound;
INSERT INTO spice_active_compound (spice_id, aromatic_compound_id, oav_value)
SELECT
  scc.spice_id,
  scc.aromatic_compound_id,
  scc.concentration_ppm / odt.odt_ppm AS oav
FROM spice_compound_concentration scc
JOIN compound_odt odt
  ON odt.aromatic_compound_id = scc.aromatic_compound_id
WHERE scc.concentration_ppm / odt.odt_ppm > 1;
```

Idempotent, déterministe, rejouable.

---

## 6. Stratégie d'acquisition des données

> **Carte blanche acquis** : priorité absolue à la fiabilité scientifique. Aucune contrainte de coût ou de temps d'ingestion.

### 6.1 Données nécessaires

| Donnée | Granularité | Volume MVP | Volume cible |
|---|---|---|---|
| `odt_ppm` par molécule | 1 ligne / molécule | 15 composés actuels | 100–200 (FlavorDB élargie) |
| `concentration_ppm` par (épice, molécule) | 1 ligne / pivot | 30 × ~10 = ~300 | 30 × ~50 = ~1 500 |

### 6.2 Sources documentaires — Pistes précises

#### Source 1 — **van Gemert, *Compilation of Odour Threshold Values*** (référence absolue pour ODT)

- **Édition** : 2nd ed., 2011 — Oliemans Punter & Partners BV, Pays-Bas — ISBN 978-90-810894-1-5.
- **Format** : livre imprimé + PDF licencié.
- **Couverture** : ~3 000 composés volatils avec ODT en air / eau / huile (médiane + range).
- **Coût indicatif** : ~150 € licence individuelle.
- **Procédure d'ingestion** : saisie manuelle en YAML (fixture versionnée), une ligne par molécule en BDD actuelle, élargi au fil de l'enrichissement.
- **Site éditeur** : `oliemans-punter.com` (à vérifier).

#### Source 2 — **FlavorDB / FlavorDB 2.0** (Garg et al., 2018) — concentrations volatiles

- **URL** : `https://cosylab.iiitd.edu.in/flavordb/` (FlavorDB 2.0 publiée 2022 — voir `https://cosylab.iiitd.edu.in/flavordb2/`).
- **Couverture** : 25 595 composés volatils, 938 ingrédients, concentrations approximatives.
- **Accès** : Web UI + API JSON (`/api/molecule/{id}`, `/api/ingredient/{id}`) + dumps bulk sur demande.
- **Licence** : académique, gratuite (Garg, N. et al., 2018, *Nucleic Acids Research*, 46(D1), D1210–D1216).
- **Procédure d'ingestion** :
  1. Construire un mapping `Spices.name` ↔ `FlavorDB.ingredient_name` (manuel pour les 30 épices).
  2. Pour chaque épice mappée, récupérer `molecules[]` avec leur `concentration` (quand disponible).
  3. Aligner `FlavorDB.molecule_name` avec `AromaticCompound.name` (rapprochement via CAS Number prioritaire, fallback nom).
- **Gotcha** : toutes les molécules de FlavorDB n'ont pas de concentration ; cas couverts uniquement pour les ingrédients étudiés en GC-MS.

#### Source 3 — **Flavornet** (Acree & Arn) — descripteurs olfactifs + ODT

- **URL** : `http://www.flavornet.org/`.
- **Couverture** : 738 composés volatils avec descripteurs olfactifs + ODT en air.
- **Accès** : HTML scraping ou téléchargement CSV.
- **Usage** : source secondaire pour valider/compléter les ODT de van Gemert, et enrichir les descripteurs olfactifs textuels (utiles pour l'UX, hors moteur de calcul).

#### Source 4 — **The Good Scents Company** — ODT commerciaux

- **URL** : `http://www.thegoodscentscompany.com/`.
- **Couverture** : ~4 000 ingrédients aromatiques (parfumerie + aromatique alimentaire), ODT + notes olfactives.
- **Accès** : site Web (consultation libre par molécule), pas d'API officielle, scraping respectueux possible (rate limit conservateur).
- **Usage** : source tertiaire de complément.

#### Source 5 — **PubChem** (NIH) — normalisation chimique

- **URL** : `https://pubchem.ncbi.nlm.nih.gov/`.
- **API** : REST gratuite (`pubchem.ncbi.nlm.nih.gov/rest/pug/`).
- **Couverture** : 100M+ molécules, CAS Number, SMILES, InChI, propriétés.
- **Usage MVP** : récupération du **CAS Number** par composé pour la déduplication et le rapprochement multi-sources. Non requis pour le calcul.

#### Source 6 — **FoodB** — base alimentaire générale

- **URL** : `https://foodb.ca/`.
- **Couverture** : ~70 000 composés alimentaires, ~1 000 aliments.
- **Usage** : moins ciblé sur les volatils que FlavorDB ; à consulter en cas de couverture manquante sur une épice spécifique.

### 6.3 Sources analytiques internes (optionnelles)

#### Analyse GC-MS (chromatographie en phase gazeuse / spectrométrie de masse)

- **Sous-traitance** : laboratoires Eurofins (FR), Phytocontrol (FR), INRAE.
- **Coût** : 80–150 € / échantillon en méthode standard HS-SPME-GC-MS.
- **Livrable** : identification + quantification des composés volatils.
- **Bénéfice** : données exactes par lot d'épice utilisé, calibrant les écarts entre FlavorDB et la réalité de l'épice cuisinée. Pertinent pour les épices premium ou exotiques absentes des bases publiques.

#### Analyse GC-Olfactométrie (GC-O)

- **Sous-traitance** : laboratoires spécialisés (INRAE, IUT Adriant, certaines facs).
- **Coût** : 300–500 € / échantillon.
- **Livrable** : Charm-values / AEDA-factors par molécule à impact.
- **Bénéfice** : ODT contextualisé à l'épice elle-même (au lieu de l'ODT abstrait du composé pur). À garder en R&D long terme.

### 6.4 Procédure d'ingestion (6 semaines indicatives)

| Sprint | Action | Livrable |
|---|---|---|
| S1 | Saisie ODT van Gemert pour les 15 molécules actuelles + 50 molécules cibles | `fixtures/compound_odt.yaml` |
| S2 | Mapping `Spices` ↔ `FlavorDB.ingredient_id` (30 lignes manuelles) | `fixtures/spice_flavordb_mapping.yaml` |
| S3 | Script `bin/console app:import:flavordb` → ingestion concentrations | `spice_compound_concentration` peuplée |
| S4 | Validation croisée avec Flavornet (écarts > 50 % à investiguer) | Rapport de cohérence en `var/data_quality_report.md` |
| S5 | (Optionnel) Compléments via GoodScents pour les ODT manquants | `fixtures/compound_odt.yaml` enrichi |
| S6 | Job `RecomputeOavTableHandler` → matérialise `spice_active_compound` | Table opérationnelle, benchmarks latence |

### 6.5 Format de stockage intermédiaire — fixtures YAML versionnées

```yaml
# fixtures/compound_odt.yaml
- compound_name: eugenol
  cas: "97-53-0"
  odt_ppm: 0.0001
  matrix: air
  source: "van Gemert (2011) p.78"

- compound_name: cinnamaldehyde
  cas: "104-55-2"
  odt_ppm: 0.0007
  matrix: air
  source: "van Gemert (2011) p.45"
```

```yaml
# fixtures/spice_compound_concentration.yaml
- spice_name: clou-de-girofle
  compound_name: eugenol
  concentration_ppm: 850000        # 85 % de l'huile essentielle
  source: "FlavorDB ingredient_id=42"

- spice_name: clou-de-girofle
  compound_name: beta-caryophyllene
  concentration_ppm: 75000
  source: "FlavorDB ingredient_id=42"
```

Ce format permet la traçabilité par source, le rejeu d'ingestion idempotent, et le diff Git lors d'enrichissements ultérieurs.

### 6.6 Commandes Symfony Console à créer

```php
// bin/console app:import:odt --file=fixtures/compound_odt.yaml
final class ImportOdtCommand extends Command { ... }

// bin/console app:import:flavordb --remote   (mode API) ou --file=var/flavordb_dump.json
final class ImportFlavorDbCommand extends Command { ... }

// bin/console app:recompute:oav
final class RecomputeOavCommand extends Command { ... }
```

---

## 7. Fichiers à créer / modifier

| Fichier | Action | Rôle |
|---|---|---|
| `src/Entity/CompoundOdt.php` | **Créer** | Entité Doctrine ODT |
| `src/Entity/SpiceCompoundConcentration.php` | **Créer** | Entité jointure concentration |
| `src/Entity/SpiceActiveCompound.php` | **Créer** | Vue matérialisée OAV-actifs |
| `src/Repository/CompoundOdtRepository.php` | **Créer** | — |
| `src/Repository/SpiceActiveCompoundRepository.php` | **Créer** | + méthode `loadOavProfilesBatch(int[] $spiceIds)` |
| `src/Repository/CandidateVetoRepository.php` | **Créer** | Requête SQL de veto biparti |
| `src/Service/Match/MortarProfileBuilder.php` | **Créer** | Construction $\Pi_M^*$ avec cache |
| `src/Service/Match/OavTanimotoScorer.php` | **Créer** | Algorithme de scoring |
| `src/Service/Match/MatchPipeline.php` | **Créer** | Orchestrateur 6 étapes |
| `src/Dto/Match/MortarRequestDto.php` | **Créer** | DTO de requête (validation IDs) |
| `src/Dto/Match/MatchResultDto.php` | **Créer** | DTO de réponse |
| `src/Controller/Api/MatchController.php` | **Créer** | Endpoint `GET /api/match` |
| `src/Message/RecomputeOavTableMessage.php` | **Créer** | Message Messenger |
| `src/MessageHandler/RecomputeOavTableHandler.php` | **Créer** | Handler du recalcul OAV |
| `src/Command/ImportOdtCommand.php` | **Créer** | Console : ingestion ODT |
| `src/Command/ImportFlavorDbCommand.php` | **Créer** | Console : ingestion FlavorDB |
| `src/Command/RecomputeOavCommand.php` | **Créer** | Console : recalcul OAV |
| `src/EventListener/SpiceConcentrationChangedListener.php` | **Créer** | Trigger Doctrine → dispatch `RecomputeOavTableMessage` |
| `fixtures/compound_odt.yaml` | **Créer** | Saisie ODT van Gemert |
| `fixtures/spice_flavordb_mapping.yaml` | **Créer** | Mapping nom-épice ↔ FlavorDB |
| `fixtures/spice_compound_concentration.yaml` | **Créer** | (Généré par import) |
| `config/packages/cache.yaml` | **Modifier** | Pool `match.mortar_profile.cache` (Redis prod) |
| `config/packages/messenger.yaml` | **Vérifier** | Transport `async` pour `RecomputeOavTableMessage` |
| `tests/Service/Match/OavTanimotoScorerTest.php` | **Créer** | Unitaires : disjoint / identique / partiel |
| `tests/Service/Match/MortarProfileBuilderTest.php` | **Créer** | Vérifie `max` par molécule |
| `tests/Repository/CandidateVetoRepositoryTest.php` | **Créer** | Intégration : mortier 1, 2, 3 épices |
| `tests/Service/Match/MatchPipelineTest.php` | **Créer** | Intégration end-to-end |
| `tests/Controller/Api/MatchControllerTest.php` | **Créer** | E2E HTTP |
| `src/Service/CompatibilityScoreService.php` | **Conserver** | Maintenu jusqu'à validation MVP, supprimé après |

---

## 8. Roadmap

### Sprint 1 — Schéma + ingestion ODT

- Création des 3 entités Doctrine.
- `doctrine:schema:update --force`.
- Commande `app:import:odt` opérationnelle.
- Saisie manuelle des 15 ODT (van Gemert).

### Sprint 2 — Ingestion FlavorDB

- Commande `app:import:flavordb` (mode API + fallback dump local).
- Mapping `spice_flavordb_mapping.yaml` (manuel).
- Premier remplissage de `spice_compound_concentration`.

### Sprint 3 — Précalcul OAV + Veto

- `RecomputeOavTableHandler` opérationnel + commande console.
- `CandidateVetoRepository::findSurvivors()` testé.
- Validation croisée Flavornet (écarts > 50 % investigués).

### Sprint 4 — Scoring + endpoint

- `OavTanimotoScorer` + tests unitaires (cas frontières : ensembles disjoints, identiques, partiels).
- `MortarProfileBuilder` + cache.
- `MatchPipeline` orchestrant les 6 étapes.
- `GET /api/match` documenté (OpenAPI).
- Tests d'intégration + E2E.

### Sprint 5 — Calibration & A/B

- Benchmark de latence sur 100 épices fictives.
- A/B test contre `CompatibilityScoreService` actuel sur 20 paires connues.
- Décision : retirer ou conserver le service legacy.

### Sprint 6 — Hardening prod

- Cache niveau 2 (réponses JSON).
- Métriques applicatives (Prometheus / Symfony Monitoring).
- Documentation utilisateur dans `docs/`.

---

## 9. Critères de validation

| # | Critère | Test |
|---|---|---|
| C1 | Latence p99 < 100 ms sur mortier 1–5 épices | Benchmark `php bin/console app:benchmark:match` |
| C2 | Une seule requête SQL pour veto + une seule pour hydratation | Profiler Symfony / `EXPLAIN ANALYZE` |
| C3 | Cache niveau 1 (profil mortier) actif et invalidé sur changement | Test d'intégration cache hit/miss |
| C4 | Aucun algorithme hors duo en production | Revue de code |
| C5 | Pour `mortar=(thym, origan)`, top 3 contient `(cumin, marjolaine, basilic)` | Test fonctionnel gastronomique |
| C6 | Complexité linéaire vérifiée (×10 catalogue → ×10 latence max) | Test de charge |
| C7 | Aucune régression sur les tests PHPUnit existants | `composer ci` passe |
| C8 | Couverture des cas frontières du scoring (vide, identique, disjoint) | Tests unitaires `OavTanimotoScorerTest` |

---

## 10. Bibliographie minimale

- Ahn, Y.-Y., Ahnert, S. E., Bagrow, J. P., & Barabási, A.-L. (2011). *Flavor network and the principles of food pairing*. Scientific Reports, 1, 196. — Fondement de l'hypothèse "épices partageant des composés ⇒ compatibles".
- van Gemert, L. J. (2011). *Compilation of Odour Threshold Values in Air, Water and Other Media* (2nd ed.). Oliemans Punter & Partners BV. — Source de référence ODT.
- Garg, N. et al. (2018). *FlavorDB: a database of flavor molecules*. Nucleic Acids Research, 46(D1), D1210–D1216. — Source des concentrations volatiles.

---

## Annexe — Glossaire

- **OAV** (Odor Activity Value) : $C_i / ODT_i$. Une molécule est perceptible ssi $OAV > 1$.
- **ODT** (Odor Detection Threshold) : concentration minimale détectable par le nez humain, en ppm. Dépend de la matrice (air / eau / huile).
- **Mortier** : ensemble d'épices d'entrée $M$ pour lequel on recherche les meilleurs candidats compatibles.
- **Profil OAV agrégé** $\Pi_M^*$ : vecteur d'OAV combinés du mortier (max par molécule).
- **Veto** : filtrage strict éliminant les candidats sans pont aromatique perceptible avec une au moins une épice du mortier.
- **Tanimoto pondéré** : version vectorielle de Jaccard intégrant l'intensité par dimension. Référence en QSAR.

---

> **Note de migration plan → racine** : Après approbation de ce plan, le présent fichier sera copié à la racine du projet sous le nom `ARCHITECTURE_MOTEUR_COMPATIBILITE.md` pour conservation hors du dossier `.claude/plans/`. Aucune autre référence n'est nécessaire pour reprendre la conception ultérieurement.
