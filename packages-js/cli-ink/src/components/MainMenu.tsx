import { Box, Text } from 'ink';
import type { ReactElement } from 'react';
import { useNavigableList } from '../hooks/useNavigableList.js';

export type MenuOption = 'dashboard' | 'resources' | 'logs' | 'quit';

export type MainMenuProps = {
  onSelect?: (option: MenuOption) => void;
  onCancel?: () => void;
};

const OPTIONS: { value: MenuOption; label: string }[] = [
  { value: 'dashboard', label: 'Dashboard' },
  { value: 'resources', label: 'Resources' },
  { value: 'logs', label: 'Logs' },
  { value: 'quit', label: 'Quit' },
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
        Arqel — Terminal UI
      </Text>
      <Text dimColor>Use arrows to navigate, enter to select, q to quit.</Text>
      <Box marginTop={1} flexDirection="column">
        {OPTIONS.map((opt, i) => {
          const active = i === index;
          return (
            <Text key={opt.value} {...(active ? { color: 'green' } : {})}>
              {active ? '> ' : '  '}
              {opt.label}
            </Text>
          );
        })}
      </Box>
    </Box>
  );
}
