# Dame – Architektur & Status-Dokumentation

## Überblick

Browser-basiertes Dame-Spiel nach deutschen Regeln (8×8, 12 Steine je Seite).
Kein Login – der Player-Token in der URL ist die einzige Zugriffskontrolle.
Echtzeit-Sync über WebSockets (Laravel Reverb), Polling als Fallback.

**Stack:** Laravel 12 · Inertia v2 · Vue 3 · SQLite · Reverb · Tailwind CSS v4

---

## Spielstatus-Zustandsmaschine

```
[Spieler 1 erstellt Spiel]
         │
         ▼
     WAITING ──── Spieler 2 öffnet Share-Link ───▶ ACTIVE
                                                       │
                               ┌───────────────────────┤
                               │  Spieler ohne gültige  │
                               │  Züge (keine Steine    │
                               │  oder eingeschlossen)  │
                               ▼
                           FINISHED
```

| Status     | Bedeutung                                          |
|------------|----------------------------------------------------|
| `waiting`  | Nur Spieler 1 verbunden; wartet auf Spieler 2      |
| `active`   | Beide Spieler verbunden; Züge möglich              |
| `finished` | Gewinner festgestellt; keine Züge mehr möglich     |

### Übergänge

- **waiting → active**: `GameController::show()` erkennt Spieler 2 am Token und setzt `status = active`.
- **active → finished**: `MoveController::store()` prüft nach jedem Zug via `CheckersGameService::getWinner()`, ob der Gegner keine Züge mehr hat.

---

## Datenbankmodell

```
games
├── uuid            (öffentliche ID, in URLs und Channels)
├── player1_token   (UUID, einziger Zugangsschlüssel für Spieler 1)
├── player2_token   (UUID, einziger Zugangsschlüssel für Spieler 2)
├── board_state     (JSON – siehe Board-Format)
├── current_turn    (1 | 2)
├── status          (waiting | active | finished)
└── winner          (1 | 2 | null)

moves
├── game_id
├── player_number   (1 | 2)
├── from_row/col, to_row/col
└── captures        (JSON – [{row, col}, …])
```

### Board-Format (JSON)

```json
{ "cells": [ null, {"player": 2, "isKing": false}, null, … ] }
```

- Flat Array mit 64 Einträgen, Index = `row * 8 + col`
- Bespielbar: Dunkle Felder `(row + col) % 2 === 1`
- Spieler 2 startet auf Reihen 0–2, Spieler 1 auf Reihen 5–7

---

## Spiellogik (CheckersGameService)

Implementiert die deutschen Dame-Regeln vollständig serverseitig. Der Client spiegelt die Logik für die Zug-Vorschau (grüne Punkte), aber der Server validiert jeden Zug nochmals.

| Regel                   | Implementierung                                                    |
|-------------------------|--------------------------------------------------------------------|
| Nur Vorwärtszüge (Mann) | `forwardDir = player === 1 ? -1 : 1`                              |
| **Schlagpflicht**       | `getAllValidMoves()` gibt ausschließlich Schlagzüge zurück, wenn welche existieren |
| **Maximierungsregel**   | Nur Sequenzen mit der höchsten Schlaganzahl werden zurückgegeben   |
| **Mehrfachschlag**      | Rekursive Kettenberechnung (`getManCaptureSequences`)             |
| **Damenwandlung**       | Erreicht ein Mann die letzte Reihe, wird er zur Dame; die Kette endet dort |
| **Dame**                | Bewegt sich beliebig weit diagonal; schlägt über beliebige Distanz (`getKingCaptureSequences`) |
| Spielende               | `isGameOver()`: Spieler hat keine gültigen Züge mehr              |

---

## Request-Ablauf: Zug ausführen

```
Browser (Spieler A)                     Server                   Browser (Spieler B)
        │                                  │                              │
        │── POST /game/{token}/moves ─────▶│                              │
        │                                  │ validate move                │
        │                                  │ applyMove()                  │
        │                                  │ update games + moves         │
        │◀─ 200 {board_state, …} ──────────│                              │
        │                                  │── broadcast MoveMade ───────▶│
        │ (board sofort aktualisiert)      │   (ShouldBroadcastNow)       │
        │── router.reload() (bg) ─────────▶│                              │ (board via WebSocket aktualisiert)
```

- Der aktive Spieler erhält das neue Board direkt als JSON-Antwort → **kein Flackern**.
- Der passive Spieler erhält es via Reverb-WebSocket (Channel `game.{uuid}`, Event `MoveMade`).
- `router.reload({ only: ['game'] })` synchronisiert Inertia im Hintergrund.
- Ist Reverb nicht gestartet, wird der Broadcast-Fehler ignoriert; Polling (5 s) dient als Fallback.

---

## Frontend-Zustandsmaschine (useCheckers)

```
[kein Stein ausgewählt]
         │
         │ Klick auf eigenen Stein
         ▼
  [Stein ausgewählt]  ←──────────── Klick daneben / anderer Stein
         │
         │ Klick auf grünen Punkt (path[0])
         ▼
  ┌─────────────────────────────────────┐
  │  path.length === 1?                 │
  │  ja ──▶ executeMove() → Server      │
  │  nein ──▶ [Kette läuft]            │
  └─────────────────────────────────────┘
         │
         │ (bei Kette: Klick auf path[jumpPathLength])
         ▼
  [nächster Schritt / letzter Schritt = executeMove()]
```

### Refs im Composable

| Ref               | Typ                | Bedeutung                                                    |
|-------------------|--------------------|--------------------------------------------------------------|
| `selectedCell`    | `Cell \| null`     | Aktuell ausgewählter Stein                                   |
| `validMoves`      | `ValidMove[]`      | Alle gültigen Züge für den ausgewählten Stein                |
| `highlightedCells`| `Cell[]` (computed)| Nächste klickbare Felder (`path[jumpPathLength]` oder `path[0]`) |
| `jumpChains`      | `ValidMove[]`      | Noch offene Ketten während eines Mehrfachschlags             |
| `jumpPathLength`  | `number`           | Wie viele Schritte der aktuellen Kette bereits bestätigt wurden |
| `isSubmitting`    | `boolean`          | HTTP-Request läuft gerade                                    |
| `moveError`       | `string \| null`   | Fehlermeldung vom Server                                     |

### ValidMove-Struktur

```typescript
{
  from_row, from_col,   // Startposition des Steins
  to_row, to_col,       // Endposition nach dem vollständigen Zug
  captures: [{row, col}, …],  // Alle geschlagenen Steine
  path: [{row, col}, …]       // Alle Zwischenlandeplätze + Endposition
                               // Länge 1 = Einfachzug
                               // Länge 2+ = Mehrfachschlag (Schritt für Schritt klickbar)
}
```

---

## WebSocket-Setup

- **Channel:** `game.{uuid}` (öffentlich, kein Auth erforderlich)
- **Event:** `MoveMade` (broadcastAs)
- **Payload:** `board_state`, `current_turn`, `status`, `winner`, `move`
- **Client:** `useEchoPublic()` aus `@laravel/echo-vue`
- **Fallback:** `usePoll(5000, { only: ['game'] })` — stoppt automatisch bei `status === 'finished'`

---

## Starten

```bash
# Alle Dienste (nur localhost):
composer run dev       # Laravel + Vite + Queue + Logs
composer run reverb    # WebSocket-Server (separates Terminal)

# Im lokalen Netzwerk erreichbar:
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0
npm run dev
# .env: VITE_REVERB_HOST=<LAN-IP>, APP_URL=http://<LAN-IP>:8000
```

---

## Tests

```bash
php artisan test --compact                              # alle Tests
php artisan test --compact tests/Unit/               # Spiellogik
php artisan test --compact tests/Feature/Game/       # HTTP + Events
```

| Testdatei                              | Abdeckung                                              |
|----------------------------------------|--------------------------------------------------------|
| `tests/Unit/CheckersGameServiceTest`   | Spiellogik: Brett, Züge, Schlagpflicht, Dame, Spielende |
| `tests/Feature/Game/GameCreationTest`  | Spiel erstellen, Join, Token-Validierung, Share-URL    |
| `tests/Feature/Game/GameMoveTest`      | Zug-Validierung, Turns, Schlagpflicht, Events, Spielende |
