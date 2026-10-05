# Integrace AI agentů a Skill

SDK `revolut-x-php` obsahuje vestavěnou podporu pro **AI vývojové asistenty a autonomní obchodní agenty** (jako Google Antigravity, Claude, Cursor, GitHub Copilot nebo vlastní agenty postavené na LangChain či AutoGPT).

---

## Co je to AI Agent Skill?

**Agent Skill** je strukturovaný balíček doménových znalostí, konvencí a operačních postupů, který učí AI asistenta spolehlivě psát, testovat a provádět obchodní operace s PHP knihovnou `janprikryl/revolutx`.

Skill je definován v repozitáři v cestě:
```text
.agents/skills/revolut-x-php/
├── SKILL.md              # Hlavní instrukce, vzory použití API a pravidla
└── references/
    └── types.md          # Kompletní definice typů, enumů a schémat odpovědí
```

### Schopnosti poskytované AI agentům

Když je skill aktivní, AI asistent automaticky rozumí:

- **Autentizaci a nastavení**: Generování Ed25519 klíčů, konfiguraci `RevolutX\Client` a ověření `client->isAuthenticated()`.
- **Exekuci s nulovým poplatkem (Maker)**: Výpočtu optimálních maker cen (`MakerOrderStrategy::calculateMakerPrice`) a odesílání `post_only` limitních příkazů pro garantovaný 0.00% poplatek.
- **Offline kalkulaci poplatků**: Použití `FeeCalculator::calculate()` pro odhad poplatků ještě před odesláním objednávky.
- **Validaci pravidel**: Offline validaci parametrů objednávky vůči limitům burzy přes `OrderPayloadBuilder::validateAgainstPairRules()`.
- **Vysoké přesnosti čísel**: Zachování decimální přesnosti pomocí řetězcových reprezentací pro všechny částky a ceny.
- **Strukturovanému zpracování chyb**: Správnému odchytávání a reakci na konkrétní výjimky (`AuthenticationException`, `RateLimitException`, `OrderValidationException`, `ApiException`).

---

## Přímé odkazy a stažení Skill souborů

Soubory skillu můžete zobrazit, stáhnout nebo přímo importovat do svého AI prostředí:

| Soubor | Popis | Přímý odkaz |
| :--- | :--- | :--- |
| **`SKILL.md` (Raw)** | Hlavní prompt a instrukce pro AI agenta | [Stáhnout SKILL.md](https://raw.githubusercontent.com/janprikryl1/revolut-x-php/main/.agents/skills/revolut-x-php/SKILL.md) |
| **`types.md` (Raw)** | Přehled typů, enumů a referencí odpovědí | [Stáhnout types.md](https://raw.githubusercontent.com/janprikryl1/revolut-x-php/main/.agents/skills/revolut-x-php/references/types.md) |
| **Složka Skillu (GitHub)** | Interaktivní prohlížeč na GitHubu | [Zobrazit .agents/skills/revolut-x-php](https://github.com/janprikryl1/revolut-x-php/tree/main/.agents/skills/revolut-x-php/) |
| **Repozitář (ZIP)** | Kompletní repozitář včetně skillu a testů | [Stáhnout main.zip](https://github.com/janprikryl1/revolut-x-php/archive/refs/heads/main.zip) |

---

## Jak skill použít s AI asistenty

### 1. Google Antigravity
Skill je umístěn v kořeni repozitáře v `.agents/skills/revolut-x-php/SKILL.md`. Antigravity jej automaticky detekuje a indexuje. Stačí zadat prompt:
> *"Načti aktuální BTC-EUR ticker přes PHP SDK a vytvoř nákupní zero-fee maker objednávku za 50 EUR."*

Agent automaticky načte skill a použije doporučené vzory.

### 2. Cursor IDE
Můžete zkopírovat obsah `SKILL.md` do svého `.cursorrules` nebo umístit pod `.cursor/rules/revolut-x-php.mdc` pro kompletní znalost SDK v Composeru Cursoru.

### 3. Claude Code / GitHub Copilot
Předejte `SKILL.md` jako kontext nebo jej vložte do systémových instrukcí při generování obchodní logiky v PHP pro Revolut X.
