import type { NextConfig } from 'next';

const config: NextConfig = {
  output: 'standalone',
  poweredByHeader: false,
  allowedDevOrigins: ['127.0.0.1', 'localhost'],
  async redirects() {
    const featuredSort = [{ type: 'query' as const, key: 'sort', value: 'featured' }];
    return [
      { source: '/shop', has: featuredSort, destination: '/shop?sort=newest', permanent: false },
      { source: '/shop/:category', has: featuredSort, destination: '/shop/:category?sort=newest', permanent: false },
    ];
  },
};

export default config;
