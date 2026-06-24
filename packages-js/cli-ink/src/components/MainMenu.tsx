import { Box, Text } from 'ink';
import type { ReactElement } from 'react';
import { useNavigableList } from '../hooks/useNavigableList.js';
import { t } from '../i18n.js';

export type MenuOption = 'dashboard' | 'resources' | 'logs' | 'quit';

export type MainMenuProps = {
  onSelect?: (option: MenuOption) => void;
  onCancel?: () => void;
};

const OPTIONS: { value: MenuOption; labelKey: string; fallback: string }[] = [
  { value: 'dashboard', labelKey: 'cli.menu.dashboard', fallback: 'Dashboard' },
  { value: 'resources', labelKey: 'cli.menu.resources', fallback: 'Resources' },
  { value: 'logs', labelKey: 'cli.menu.logs', fallback: 'Logs' },
  { value: 'quit', labelKey: 'cli.menu.quit', fallback: 'Quit' },
];

export function MainMenu({ onSelect, onCancel }: MainMenuProps): ReactElement {
  const { index } = useNavigableList({
    itemCount: OPTIONS.length,
    onSelect: (i) => {
      const opt = OPTIONS[i];
      if (opt) onSelect?.(opt.value);
    },
    onCancel,
  });

  return (
    <Box flexDirection="column" padding={1} borderStyle="round" borderColor="cyan">
      <Text bold color="cyan">
        {t('cli.menu.title', 'Arqel — Terminal UI')}
      </Text>
      <Text dimColor>
        {t('cli.menu.hint', 'Use arrows to navigate, enter to select, q to quit.')}
      </Text>
      <Box marginTop={1} flexDirection="column">
        {OPTIONS.map((opt, i) => {
          const active = i === index;
          return (
            <Text key={opt.value} {...(active ? { color: 'green' } : {})}>
              {active ? '> ' : '  '}
              {t(opt.labelKey, opt.fallback)}
            </Text>
          );
        })}
      </Box>
    </Box>
  );
}
