import { join } from 'node:path';
import { Box, Text } from 'ink';
import type { ReactElement } from 'react';
import { type UseDataSourceOptions, useDataSource } from '../hooks/useDataSource.js';
import { useNavigableList } from '../hooks/useNavigableList.js';
import { formatNumber, t } from '../i18n.js';

export type ResourceEntry = {
  slug: string;
  label: string;
  count: number;
  description?: string;
};

export type ResourceBrowserProps = {
  dataDir: string;
  pollMs?: number;
  onCancel?: () => void;
  ioOverrides?: Pick<UseDataSourceOptions, 'readFile' | 'fileExists'>;
};

export function ResourceBrowser({
  dataDir,
  pollMs = 0,
  onCancel,
  ioOverrides,
}: ResourceBrowserProps): ReactElement {
  const filePath = join(dataDir, 'resources.json');
  const { data, loading, error } = useDataSource<ResourceEntry[]>(filePath, {
    pollMs,
    ...(ioOverrides?.readFile ? { readFile: ioOverrides.readFile } : {}),
    ...(ioOverrides?.fileExists ? { fileExists: ioOverrides.fileExists } : {}),
  });

  const items = data ?? [];
  const { index } = useNavigableList({
    itemCount: items.length,
    ...(onCancel ? { onCancel } : {}),
  });

  if (loading) return <Text>{t('cli.resources.loading', 'Loading resources…')}</Text>;
  if (error) {
    return (
      <Text color="red">
        {t('cli.error.prefix', 'Error:')} {error}
      </Text>
    );
  }
  if (items.length === 0) {
    return <Text color="yellow">{t('cli.resources.empty', 'No resources found.')}</Text>;
  }

  const selected = items[index] ?? items[0];

  return (
    <Box flexDirection="row">
      <Box flexDirection="column" borderStyle="round" borderColor="cyan" paddingX={1} width={28}>
        <Text bold color="cyan">
          {t('cli.resources.heading', 'Resources')}
        </Text>
        {items.map((item, i) => {
          const active = i === index;
          return (
            <Text key={item.slug} {...(active ? { color: 'green' } : {})}>
              {active ? '> ' : '  '}
              {item.label} ({formatNumber(item.count)})
            </Text>
          );
        })}
      </Box>
      <Box
        flexDirection="column"
        marginLeft={1}
        borderStyle="round"
        borderColor="magenta"
        paddingX={1}
        flexGrow={1}
      >
        <Text bold color="magenta">
          {selected?.label ?? '-'}
        </Text>
        <Text dimColor>
          {t('cli.resources.slug', 'slug:')} {selected?.slug ?? '-'}
        </Text>
        <Text>
          {t('cli.resources.records', 'Records:')} {formatNumber(selected?.count ?? 0)}
        </Text>
        {selected?.description ? <Text>{selected.description}</Text> : null}
      </Box>
    </Box>
  );
}
