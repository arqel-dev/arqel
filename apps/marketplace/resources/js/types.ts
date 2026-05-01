export type PublisherSnapshot = {
  id: number;
  slug: string;
  name: string;
  avatar_url?: string | null;
  verified?: boolean;
};

export type Publisher = PublisherSnapshot & {
  bio?: string | null;
  website_url?: string | null;
  github_url?: string | null;
  twitter_handle?: string | null;
  user_id?: number | null;
};

export type PublisherStats = {
  plugins_count: number;
  total_downloads: number;
  avg_rating: number;
};

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
  reviews_count?: number;
  updated_at?: string | null;
  publisher?: PublisherSnapshot | null;
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
