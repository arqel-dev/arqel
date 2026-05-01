import { render, useApp } from 'ink';
import meow from 'meow';
import type { ReactElement } from 'react';
import { useState } from 'react';
import { Dashboard } from './components/Dashboard.js';
import { LogTailer } from './components/LogTailer.js';
import { MainMenu, type MenuOption } from './components/MainMenu.js';
import { ResourceBrowser } from './components/ResourceBrowser.js';

const cli = meow(
  `
  Usage
    $ arqel-ink [command] [options]

  Commands
    dashboard               Live dashboard widgets (queries, users, errors, AI tokens)
    resources               Browse Arqel resources (read-only)
    logs <file>             Tail a log file with level highlighting
    (no command)            Open the main menu

  Options
    --data-dir <path>       Directory with Arqel data manifests (default: ./.arqel-data)
    --follow, -f            Follow log file (logs subcommand)
    --help                  Show this help

  Examples
    $ arqel-ink dashboard --data-dir=./.arqel-data
    $ arqel-ink resources
    $ arqel-ink logs storage/logs/laravel.log --follow
`,
  {
    importMeta: import.meta,
    flags: {
      dataDir: { type: 'string', default: process.env['ARQEL_DATA_DIR'] ?? './.arqel-data' },
      follow: { type: 'boolean', shortFlag: 'f', default: false },
    },
  },
);

type Screen =
  | { kind: 'menu' }
  | { kind: 'dashboard' }
  | { kind: 'resources' }
  | { kind: 'logs'; file: string };

function App({ initial }: { initial: Screen }): ReactElement | null {
  const { exit } = useApp();
  const [screen, setScreen] = useState<Screen>(initial);

  if (screen.kind === 'menu') {
    return (
      <MainMenu
        onSelect={(opt: MenuOption) => {
          if (opt === 'quit') exit();
          else if (opt === 'dashboard') setScreen({ kind: 'dashboard' });
          else if (opt === 'resources') setScreen({ kind: 'resources' });
          else if (opt === 'logs') exit();
        }}
        onCancel={() => exit()}
      />
    );
  }

  if (screen.kind === 'dashboard') {
    return <Dashboard dataDir={cli.flags.dataDir} />;
  }

  if (screen.kind === 'resources') {
    return <ResourceBrowser dataDir={cli.flags.dataDir} onCancel={() => exit()} />;
  }

  return <LogTailer filePath={screen.file} follow={cli.flags.follow} />;
}

const command = cli.input[0];
let initial: Screen = { kind: 'menu' };
if (command === 'dashboard') initial = { kind: 'dashboard' };
else if (command === 'resources') initial = { kind: 'resources' };
else if (command === 'logs') {
  const file = cli.input[1];
  if (!file) {
    console.error('Error: `logs` requires a file path. Try: arqel-ink logs <file>');
    process.exit(1);
  }
  initial = { kind: 'logs', file };
}

render(<App initial={initial} />);
