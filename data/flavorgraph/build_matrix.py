#!/usr/bin/env python3
"""Offline: FlavorGraph embeddings -> cosine affinity matrix (clamped [0,1]).

Lit le crosswalk (slug -> noeud FlavorGraph), resout chaque noeud vers son
embedding 300D, calcule la similarite cosinus par paire, clamp a [0,1]
(max(0, cos)) et emet une ligne par paire non ordonnee (a < b).

La distribution du cosinus brut est basse et compressee (median ~0.16). On
emet donc le RANG-PERCENTILE [0,1] comme `affinity` (score interpretable
"meilleure que X% des paires", seuils enum et blend equilibres, classement
top-N inchange car transfo monotone). Le cosinus brut est conserve dans la
colonne `cosine` pour tracabilite.

Entrees (repertoire courant / repo) :
  - fixtures/spice_flavorgraph_mapping.yaml   (spice_slug, flavorgraph_node, status)
  - data/flavorgraph/nodes_191120.csv         (node_id, name, ...)
  - data/flavorgraph/FlavorGraph_embeddings.pickle  (node_id:str -> np.ndarray[300])

Sortie :
  - data/flavorgraph/pairing_matrix.csv       (spice_slug_a, spice_slug_b, affinity, cosine)
"""
from __future__ import annotations

import csv
import os
import pickle
import re
import sys

import numpy as np

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
CROSSWALK = os.path.join(ROOT, "fixtures", "spice_flavorgraph_mapping.yaml")
NODES = os.path.join(ROOT, "data", "flavorgraph", "nodes_191120.csv")
EMB = os.path.join(ROOT, "data", "flavorgraph", "FlavorGraph_embeddings.pickle")
OUT = os.path.join(ROOT, "data", "flavorgraph", "pairing_matrix.csv")


def load_crosswalk(path: str) -> list[tuple[str, str]]:
    slug, node = None, None
    pairs = []
    for line in open(path, encoding="utf-8"):
        m = re.match(r"^- spice_slug:\s*(\S+)", line)
        if m:
            if slug and node and node != "null":
                pairs.append((slug, node))
            slug, node = m.group(1), None
            continue
        m = re.match(r"^\s+flavorgraph_node:\s*(.+?)\s*$", line)
        if m:
            node = m.group(1)
    if slug and node and node != "null":
        pairs.append((slug, node))
    return pairs


def main() -> int:
    name2id = {}
    with open(NODES, newline="", encoding="utf-8") as f:
        r = csv.reader(f)
        next(r)
        for row in r:
            if len(row) >= 4 and row[3] == "ingredient":
                name2id[row[1]] = row[0]

    with open(EMB, "rb") as f:
        emb = pickle.load(f)

    slugs, vectors, unresolved = [], [], []
    for slug, node in load_crosswalk(CROSSWALK):
        nid = name2id.get(node)
        if nid is None or nid not in emb:
            unresolved.append((slug, node))
            continue
        slugs.append(slug)
        vectors.append(np.asarray(emb[nid], dtype=np.float64))

    if unresolved:
        print(f"[warn] {len(unresolved)} slugs sans embedding (ignores): {unresolved}", file=sys.stderr)

    n = len(slugs)
    mat = np.vstack(vectors)
    norms = np.linalg.norm(mat, axis=1, keepdims=True)
    unit = mat / np.where(norms == 0, 1.0, norms)
    cos = np.clip(unit @ unit.T, 0.0, 1.0)

    pairs = [(i, j) for i in range(n) for j in range(i + 1, n)]
    cosines = np.array([cos[i, j] for i, j in pairs])

    order = cosines.argsort(kind="stable")
    ranks = np.empty(len(pairs), dtype=np.float64)
    ranks[order] = np.arange(len(pairs), dtype=np.float64)
    percentile = ranks / max(len(pairs) - 1, 1)

    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["spice_slug_a", "spice_slug_b", "affinity", "cosine"])
        for k, (i, j) in enumerate(pairs):
            w.writerow([slugs[i], slugs[j], f"{percentile[k]:.6f}", f"{cosines[k]:.6f}"])

    print(f"[ok] {n} epices, {len(pairs)} paires -> {OUT} (affinity = rang-percentile, cosine = brut)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
