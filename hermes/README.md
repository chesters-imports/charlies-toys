# Hermes · CO.TOY-005-HERMES

Charlie's Toys mailer — lover first, CEO second. **Address + letter** → Pocket Desktop **CASE STREET MAIL** box (`inbox`).

Desk can also **file existing leaves** by dropping them on the mailbox (no Hermes required).

## Run

**Pocket Desktop must be running** (inbox installed: `-ins inbox`).

```bat
cd C:\ALICE_BOX\charlies-toys\hermes\prod
run-hermes.bat
```

- Hermes port **43169**
- Posts to Pocket `http://127.0.0.1:43167/api/mail/send`
- Override pocket URL: `set POCKET_DESKTOP_URL=http://127.0.0.1:43167`

## Flow

1. Desk: `-ins inbox` → copy **Receive.address** (e.g. `ADM·cork`)
2. Hermes: To = that address · subject · payload · SEND
3. Chip lands **held in inbox** (tag `inbox-held`, not on felt)
4. Inbox shows **you've got mail** after reload / poll
5. Desk shell later: open / take out (v0: papers please on leaf ids; take via API)

## Not yet

- Full inbox UI list on the desk object (dblclick tray)
- Agent one-liner CLI (use POST `/api/mail/send` on pocket or Hermes `/api/send`)
