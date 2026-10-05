# AI Agent Integration & Skill

The `revolut-x-php` SDK includes built-in support for **AI coding assistants and autonomous trading agents** (such as Google Antigravity, Claude, Cursor, GitHub Copilot, and custom LangChain/AutoGPT agents).

---

## What is the AI Agent Skill?

An **Agent Skill** is a structured package of domain knowledge, conventions, and operational workflows that teaches an AI assistant how to reliably write, test, and execute trading workflows with the `janprikryl/revolutx` PHP SDK.

The skill is defined in:
```text
.agents/skills/revolut-x-php/
├── SKILL.md              # Core instructions, API patterns, and rules
└── references/
    └── types.md          # Complete type definitions, enums, and response schemas
```

### Capabilities Provided to AI Agents

When the skill is active, the AI assistant automatically understands:

- **Authentication & Setup**: Generating Ed25519 keys, configuring `RevolutX\Client`, and verifying `client->isAuthenticated()`.
- **Zero-Fee Maker Execution**: Calculating optimal maker prices (`MakerOrderStrategy::calculateMakerPrice`) and submitting `post_only` limit orders to ensure a 0.00% maker fee.
- **Offline Fee Projections**: Using `FeeCalculator::calculate()` to project fees before placing orders.
- **Rule Validation**: Validating payloads offline against exchange limits using `OrderPayloadBuilder::validateAgainstPairRules()`.
- **High-Precision Decimals**: Preserving decimal precision using string representations for all amounts and prices.
- **Structured Error Handling**: Correctly catching and reacting to specific exceptions (`AuthenticationException`, `RateLimitException`, `OrderValidationException`, `ApiException`).

---

## Direct Downloads & Skill Links

You can view, download, or directly import the skill files into your AI workspace:

| Asset | Description | Direct Link |
| :--- | :--- | :--- |
| **`SKILL.md` (Raw)** | Core AI agent prompt and instructions | [Download SKILL.md](https://raw.githubusercontent.com/janprikryl1/revolut-x-php/main/.agents/skills/revolut-x-php/SKILL.md) |
| **`types.md` (Raw)** | Types, enums, and response references | [Download types.md](https://raw.githubusercontent.com/janprikryl1/revolut-x-php/main/.agents/skills/revolut-x-php/references/types.md) |
| **Skill Folder (GitHub)** | Interactive folder browser on GitHub | [View .agents/skills/revolut-x-php](https://github.com/janprikryl1/revolut-x-php/tree/main/.agents/skills/revolut-x-php/) |
| **Repository (ZIP)** | Complete repository including skill & tests | [Download main.zip](https://github.com/janprikryl1/revolut-x-php/archive/refs/heads/main.zip) |

---

## How to Use with AI Assistants

### 1. Google Antigravity
The skill is located at `.agents/skills/revolut-x-php/SKILL.md` in the workspace root. Antigravity automatically detects and indexes it. You can simply prompt:
> *"Fetch the latest BTC-EUR ticker using the PHP SDK and place a zero-fee maker buy order for 50 EUR."*

The agent will automatically load the skill and use the recommended patterns.

### 2. Cursor IDE
You can copy `SKILL.md` into your `.cursorrules` file or add it under `.cursor/rules/revolut-x-php.mdc` to equip Cursor's Composer with complete knowledge of the SDK.

### 3. Claude Code / GitHub Copilot
Pass `SKILL.md` as context or include it in your system instructions when prompting models to generate PHP trading logic for Revolut X.
