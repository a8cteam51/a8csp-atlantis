# .agents — Atlantis Plugin Agent Skills

This folder contains plugin-specific skills for AI coding assistants working on the Atlantis WordPress plugin.

## Structure

```text
.agents/
├── README.md
└── skills/
    ├── atlantis-architecture/SKILL.md  ← Plugin internals, modules, bootstrap, settings model
    ├── atlantis-autoupdates/SKILL.md   ← Autoupdates rules, PAF compatibility, per-plugin toggles
    ├── atlantis-messages/SKILL.md      ← Messages CRUD, storage, list table flows
    ├── atlantis-tracking/SKILL.md      ← Tracking integrations and env gates
    ├── atlantis-colophon/SKILL.md      ← Credits rendering and shortcode behavior
    └── atlantis-testing/SKILL.md       ← Integration tests, CI workflows, npm/composer consistency
```

## Which Skill to Use

| Task | Skill |
| ------ | ------- |
| Working on Atlantis core/plugin architecture or module lifecycle | `atlantis-architecture` |
| Changing auto-update behavior or admin toggle logic | `atlantis-autoupdates` |
| Changing Messages module data/UI behavior | `atlantis-messages` |
| Changing Tracking integrations or environment behavior | `atlantis-tracking` |
| Changing Colophon output or shortcode behavior | `atlantis-colophon` |
| Writing/running tests or fixing CI/lint failures | `atlantis-testing` |

## Recommended: WordPress Agent-Skills

This plugin also benefits from the community [WordPress/agent-skills](https://github.com/WordPress/agent-skills). These are not bundled:

| Skill | Relevance |
| ------- | ----------- |
| `wp-plugin-development` | Plugin architecture, hooks, activation, settings, security. |
| `wp-block-development` | Gutenberg-related patterns if Atlantis block integrations are added. |
| `wp-rest-api` | REST conventions when adding endpoints. |
| `wp-performance` | Profiling and optimization. |
| `wp-wpcli-and-ops` | WP-CLI operations and automation. |
| `wp-playground` | Local development flows. |
| `wp-phpstan` | Static analysis setup and fixes. |
| `wordpress-router` | Repo classification and workflow routing. |
| `wp-project-triage` | Project/tooling detection. |
