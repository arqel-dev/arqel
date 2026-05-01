export type Plugin = {
  id: number;
  slug: string;
  name: string;
  description: string;
  type: string;
  github_url: string;
  license: string;
  composer_package?: string | null;
  npm_package?: string | null;
  latest_version?: string | null;
  featured?: boolean;
  trending_score?: number;
  install_count?: number;
  stars?: number;
  price_cents?: number;
  currency?: string;
};

export type PluginCategory = {
  id: number;
  slug: string;
  name: string;
  description?: string | null;
  parent_id?: number | null;
};

export type PluginVersion = {
  id: number;
  version: string;
  changelog?: string | null;
  released_at?: string | null;
};

export type PluginReview = {
  id: number;
  stars: number;
  comment?: string | null;
  helpful_count?: number;
  verified_purchaser?: boolean;
  created_at?: string;
};

export type Paginator<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  links?: Array<{ url: string | null; label: string; active: boolean }>;
};
