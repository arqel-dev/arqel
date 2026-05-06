/**
 * Pure parser for `PLANNING/03-adrs.md`.
 *
 * Each ADR is delimited by a level-2 heading of the form `## ADR-NNN: <title>`.
 * The body extends until the next `## ADR-` heading or the end of the file.
 * Status is extracted from a `**Status:**`/`Status:` (or PT-BR `**Estado:**`)
 * line near the top of the body when present.
 */

export interface ParsedAdr {
  /** Zero-padded 3-digit ID (e.g. `"001"`, `"016"`). */
  id: string;
  /** Title text after the `ADR-NNN:` prefix. */
  title: string;
  /** Status string when present, otherwise `null`. */
  status: string | null;
  /** Full markdown body, excluding the title heading line itself. */
  body: string;
}

const ADR_HEADING = /^##\s+ADR-(\d+)\s*:\s*(.+?)\s*#*\s*$/;

/**
 * Normalises an ADR ID to a 3-digit string. Returns `null` when the input
 * does not match an accepted form.
 *
 * Accepted forms (case-insensitive): `"001"`, `"1"`, `"ADR-001"`, `"adr-1"`,
 * `"ADR001"`. Whitespace is trimmed.
 */
export function normalizeAdrId(input: string): string | null {
  if (typeof input !== 'string') return null;
  const trimmed = input.trim();
  if (trimmed === '') return null;
  const match = /^(?:adr[-_ ]?)?(\d{1,4})$/i.exec(trimmed);
  if (!match) return null;
  const digits = match[1];
  if (!digits) return null;
  return digits.padStart(3, '0');
}

/**
 * Parses the contents of `03-adrs.md` into a Map keyed by 3-digit ID.
 */
export function parseAdrs(source: string): Map<string, ParsedAdr> {
  const lines = source.split(/\r?\n/);
  const out = new Map<string, ParsedAdr>();

  let inFence = false;
  let currentId: string | null = null;
  let currentTitle = '';
  let currentBody: string[] = [];

  const flush = (): void => {
    if (currentId === null) return;
    const body = currentBody.join('\n').replace(/^\n+|\n+$/g, '');
    out.set(currentId, {
      id: currentId,
      title: currentTitle,
      status: extractStatus(body),
      body,
    });
  };

  for (const line of lines) {
    if (/^\s*```/.test(line)) {
      inFence = !inFence;
      if (currentId !== null) currentBody.push(line);
      continue;
    }

    if (!inFence) {
      const headingMatch = ADR_HEADING.exec(line);
      if (headingMatch) {
        flush();
        const digits = headingMatch[1] ?? '';
        currentId = digits.padStart(3, '0');
        currentTitle = (headingMatch[2] ?? '').trim();
        currentBody = [];
        continue;
      }
    }

    if (currentId !== null) currentBody.push(line);
  }
  flush();

  return out;
}

/**
 * Extracts the Status (or PT-BR "Estado") value from the top of an ADR body.
 * Tolerates Markdown bold markers and a trailing `• Data: ...` segment.
 */
function extractStatus(body: string): string | null {
  const lines = body.split(/\r?\n/);
  let scanned = 0;
  for (const line of lines) {
    const trimmed = line.trim();
    if (trimmed === '') continue;
    if (trimmed.startsWith('#')) break;

    // Strip leading bold/asterisks then look for "Status:" or "Estado:".
    const stripped = trimmed.replace(/^\*+\s*/, '');
    const match = /^(Status|Estado)\s*:\s*\*{0,2}\s*([^*•\n]+?)\s*(?:\*{0,2}\s*(?:•|$))/i.exec(
      stripped,
    );
    if (match) {
      const value = (match[2] ?? '').trim();
      return value === '' ? null : value;
    }

    scanned += 1;
    if (scanned >= 6) break;
  }
  return null;
}
