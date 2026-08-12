# Hermes · CO.TOY-005-HERMES

Charlie's Toys mailer — lover first, CEO second.

**Two routes from one To field:**

| To | Goes to |
|----|---------|
| `the.archivist` (desk inbox address) | **SophiaDesk** chip in HERMES MAIL |
| `dr` / `drx` / `osx` / `io` / `ab` / … | **TERMINALS** station MAIL room |

Desk can also **file existing leaves** by dropping them on the mailbox (no Hermes required).

## Run

**SophiaDesk** (for desk mail) and/or **TERMINALS** (for station mail) should be running.

```bat
cd C:\ALICE_BOX\charlies-toys\hermes\prod
run-hermes.bat
```

- Hermes port **43169**
- Desk: `POCKET_DESKTOP_URL` / `SOPHIA_DESK_URL` → `http://127.0.0.1:43167`
- TERMINALS: `TERMINALS_URL` → `http://127.0.0.1:43101` · inject token `SDK_MAIL_INJECT_TOKEN` (default `wire-whisper`)

## Flow · desk

1. Desk inbox at `the.archivist`
2. Hermes: To = `the.archivist` · SEND
3. Chip held in inbox · take out on the felt

## Flow · TERMINALS

1. TERMINALS ROM up on :43101
2. Hermes: To = `drx` (or `osx`, `io`, …) · SEND
3. Message appears in that station’s MAIL room

## Reverse · TERMINALS → desk

Compose in TERMINALS MAIL → To: **DESK · the.archivist** → chip on Sophia (desk must be running).

## Parcels (desk shipping box = this ROM)

Hermes **is** the shipping counter. Mode **PARCEL**:

1. SophiaDesk running (catalog from that desk’s library)
2. Pick **leaf · card · envelope (deck)** 
3. To: desk address (`the.archivist` · later `k.moire`)
4. SEND → files into that mailbox; open HERMES MAIL on the desk and **take**

**Not for TERMINALS** — station wire stays text-only.

## Girl network (three desks)

| Address | Desk | Port |
|---------|------|------|
| `the.archivist` | SophiaDesk | 43167 |
| `the.detective` | CassandraDesk | 43168 |
| `the.lover` | AvaDesk | 43171 |

**Law (honest thrash):** uid is local · address is global · ship **remints an instance** on the home desk. Source paper stays; scar `Mail.sent_to` / tag `mailed-out`. Dest has `Mail.origin` / `Mail.instance_of`.

Preferred long-term (shared clay, presence only) is not this — this is the network rope so the girls are not islands.

Env overrides: `SOPHIA_DESK_URL`, `CASSANDRA_DESK_URL`, `AVA_DESK_URL`.

## Not yet

- Terminal mail stored as chips (still `mail/messages/*.json` on the wire)
- Shared single library (preferred path) — still three processes + instance ship
