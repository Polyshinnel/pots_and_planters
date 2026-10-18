'use client';

import { Anchor, Button, Container, createTheme, TextInput } from '@mantine/core';

export const theme = createTheme({
  primaryColor: 'olive',
  primaryShade: 6,
  colors: {
    // 4 is the verified legacy accent; the other stops are derived UI shades.
    olive: ['#f6f6f3', '#ebede5', '#d7d9cd', '#babda9', '#989b86', '#858970', '#6e725b', '#595d49', '#474b39', '#333629'],
  },
  black: '#333333',
  white: '#ffffff',
  fontFamily: 'var(--font-lato), Arial, Helvetica, sans-serif',
  headings: { fontFamily: 'var(--font-lato), Arial, Helvetica, sans-serif', fontWeight: '600' },
  defaultRadius: 'md',
  radius: { xs: '4px', sm: '5px', md: '10px', lg: '15px', xl: '20px' },
  spacing: { xs: '8px', sm: '16px', md: '24px', lg: '32px', xl: '48px' },
  breakpoints: { xs: '36em', sm: '48em', md: '62em', lg: '76.25em', xl: '88.75em' },
  shadows: { sm: '0 2px 8px rgb(0 0 0 / 8%)' },
  focusRing: 'auto',
  focusClassName: 'site-focus',
  components: {
    Container: Container.extend({ defaultProps: { size: 1420, px: { base: 'sm', sm: 'lg' } } }),
    Button: Button.extend({ defaultProps: { color: 'olive', radius: 'md', size: 'md' } }),
    Anchor: Anchor.extend({ defaultProps: { c: 'olive.8', underline: 'hover' } }),
    TextInput: TextInput.extend({ defaultProps: { size: 'md', radius: 'xs' } }),
  },
  other: {
    layers: { floating: 100, drawer: 300 },
  },
});
