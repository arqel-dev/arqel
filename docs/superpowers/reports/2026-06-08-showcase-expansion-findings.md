# Showcase Expansion — Round-22 Candidate Findings

> Bugs/gaps surfaced WHILE building the showcase expansion (before the formal Round 22 detection workflow). Each is verified, with evidence. The Round 22 loop (Phase 7) will adversarially re-verify + classify these and file issues for the confirmed framework bugs.

## CANDIDATE #1 — enforceMorphMap + LogsActivity causer → ClassMorphViolationException (500 on every write)

**Surfaced:** Task 2.2 (Attachment morph + app-level enforceMorphMap).
**Severity (suspected):** HIGH — any app following the documented best practice breaks on every authenticated write.

**The gap:** `Relation::enforceMorphMap()` runs in strict mode (`merge=false`), so any model used polymorphically but absent from the map throws `Illuminate\Database\ClassMorphViolationException`. The framework's `LogsActivity` concern (spatie/laravel-activitylog) associates the authenticated `User` as the activity **causer**, which is a `MorphTo` relation (`packages/audit/vendor/spatie/laravel-activitylog/src/Models/Activity.php:78`). So the auth model participates polymorphically — exactly like the subject.

**Why it's untested at framework level:** the morph-alias work (#72/#190) and `packages/audit/tests/Feature/RecordActivityMorphMapTest.php` cover the activity **subject** only — that test constructs `Activity` rows manually (`$activity->subject_type = $model->getMorphClass()`, line 59) and never authenticates a causer that gets persisted through the strict morph map. The **causer-under-strict-morph-map** path is uncovered.

**Reproduction (showcase):** with `enforceMorphMap(['post'=>Post::class, ...])` active (NOT mapping User) + a model using `LogsActivity` + an authenticated user, `ShowcaseSmokeTest::test_post_store_validates_then_creates` and `OrderWorkflowTest` both threw `ClassMorphViolationException: No morph map defined for model [App\Models\User]` on write. App-level workaround: add `'user' => User::class` to the map (done in Task 2.2).

**Suggested framework fix:** when audit (`LogsActivity`) is active and a morph map is enforced, either (a) auto-register the configured auth provider model(s) in the morph map, or (b) document prominently that enabling `enforceMorphMap` requires mapping the auth User model. Add a test that authenticates a causer under an enforced morph map and asserts the write succeeds + the causer_type stores the alias.

**Distinct from:** #72 (versioning prune morphclass), #190 (audit RecordActivityController subject_type alias) — those handle the *subject*; this is the *causer*.
