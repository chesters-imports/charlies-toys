# Story sheets (author-friendly)

Fill these instead of wrestling giant Python dicts.

## Concept sheet

Copy `_concept.template.yaml` → `concepts/my-id.yaml`  
Fill the fields. Blank is fine.

## Thoughts (random bare `think`)

Edit `thoughts-void.txt` — one line per possible thought.

## Combines (`consider A with B`)

Edit `combines.yaml` — each pair is a short form.

## Build into the game

```text
cd fill-in
python tools/sheets_to_story.py
```

Writes `story_from_sheets.py` for review, **or** use `--merge` to print instructions.

For now the live game still reads `story.py`.  
Workflow: edit sheets → run tool → paste / merge into story.py  
(or we wire the engine to load sheets next).

## Form

Open `concept_form.html` in a browser. Fill fields. Copy the YAML it generates into `sheets/concepts/`.
