import { join } from 'node:path';
import { Box, Text } from 'ink';
import type { ReactElement } from 'react';
import { type UseDataSourceOptions, useDataSource } from '../hooks/useDataSource.js';
import { t } from '../i18n.js';

export type DashboardSnapshot = {
  queriesPerSec: number;
  activeUsers: number;
  errors: number;
  aiTokens: number;
};

export type DashboardProps = {
  dataDir: string;
  pollMs?: number;
  /** Test seam: forwarded to useDataSource for fs injection. */
  ioOverrides?: Pick<UseDataSourceOptions, 'readFile' | 'fileExists'>;
};

type TileProps = {
  label: string;
  value: string;
  color: string;
};

function Tile({ label, value, color }: TileProps): ReactElement {
  return (
    <Box
      flexDirection="column"
      paddingX={1}
      paddingY={0}
      marginRight={1}
      borderStyle="round"
      borderColor={color}
      width={22}
    >
      <Text dimColor>{label}</Text>
      <Text bold color={color}>
        {value}
      </Text>
    </Box>
  );
}

export function Dashboard({ dataDir, pollMs = 1000, ioOverrides }: DashboardProps): ReactElement {
  const filePath = join(dataDir, 'dashboard.json');
  const { data, loading, error } = useDataSource<DashboardSnapshot>(filePath, {
    pollMs,
    ...(ioOverrides?.readFile ? { readFile: ioOverrides.readFile } : {}),
    ...(ioOverrides?.fileExists ? { fileExists: ioOverrides.fileExists } : {}),
  });

  if (loading) {
    return <Text>{t('cli.dashboard.loading', 'Loading dashboard…')}</Text>;
  }
  if (error) {
    return (
      <Text color="red">
        {t('cli.error.prefix', 'Error:')} {error}
      </Text>
    );
  }
  if (!data) {
    return <Text color="yellow">{t('cli.dashboard.empty', 'No data available.')}</Text>;
  }

  return (
    <Box flexDirection="column">
      <Text bold color="cyan">
        {t('cli.dashboard.title', 'Arqel Dashboard')}
      </Text>
      <Box marginTop={1} flexDirection="row">
        <Tile
          label={t('cli.dashboard.tile.queries_per_sec', 'Queries / sec')}
          value={String(data.queriesPerSec)}
          color="green"
        />
        <Tile
          label={t('cli.dashboard.tile.active_users', 'Active users')}
          value={String(data.activeUsers)}
          color="cyan"
        />
      </Box>
      <Box flexDirection="row">
        <Tile
          label={t('cli.dashboard.tile.errors_5m', 'Errors (5m)')}
          value={String(data.errors)}
          color="red"
        />
        <Tile
          label={t('cli.dashboard.tile.ai_tokens', 'AI tokens')}
          value={String(data.aiTokens)}
          color="magenta"
        />
      </Box>
    </Box>
  );
}
