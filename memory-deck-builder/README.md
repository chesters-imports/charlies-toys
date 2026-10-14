```
=================================================
  KDE FUGUE INC. · MEMORY DECK BUILDER
  CO.KDE-003-MDB · the second screen
=================================================
```

**Maker:** KDE Fugue Inc. · `CO.KDE`  
**Shelf:** this folder lives in `charlies-toys/` with the other KDE toys. Charlie's Toys is the shelf, not the maker. Not a `CO.TOY`.

The board where lore cards are made, sorted, and edged. Pocket Go is the house. This is the desk that stays open while you curate it.

A card is born here. It does not remember the page you were standing on. Paste a crate id, copied from a Pocket Go cabinet, into **found** when you know a room where someone should discover it. Paste as many as you want. Drag from one card's edge to another when the two cards touch. Those are different facts.

### Run (Deck Host)

```bat
cd C:\ALICE_BOX\charlies-toys\memory-deck-builder\prod
run-deck.bat
```

- Port **43173**
- Library defaults to `cards/` (markdown). **Bring cards from the trays** copies a handful out of `pocket-go/castaways/~hosts/trays/` into this deck. The parked files stay put. The window title is the Deck Host rail. Card making sits on the row under it.
- New cards are `.md` with `environment: lorecard`. BIOS in Pocket Go can edit them once that folder is a `roam.` library. Do not turn them into `.chip` or `.txt`.
- Board positions and pile names live in `prod/safe_box/board.json`. They are not birth stamps on the card.

### On a card

| Field | Means |
|---|---|
| title, class, line, house | what you sort by |
| `found` | crate ids of rooms where this card can be found |
| `edges` | crate ids of other cards this one touches |
| `from`, `source_crate` | old birth stamp. Clear it when the card should stop claiming an original home. |
