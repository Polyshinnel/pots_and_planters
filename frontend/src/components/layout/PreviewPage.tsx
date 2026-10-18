import { Container, Stack, Text, Title } from '@mantine/core';

export function PreviewPage({ title, children }: { title: string; children: React.ReactNode }) {
  return <Container py={{ base: 'xl', sm: 80 }}>
    <Stack gap="md" maw={680}>
      <Text size="xs" c="olive.8" tt="uppercase" fw={700}>FE-01 · Temporary layout preview</Text>
      <Title order={1}>{title}</Title>
      {children}
    </Stack>
  </Container>;
}
