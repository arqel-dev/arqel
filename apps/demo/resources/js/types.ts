export interface FieldDefinition {
  name: string;
  type: string;
  required?: boolean;
  prompt?: string;
  options?: string[];
  fromField?: string;
  classifyFromFields?: string[];
}

export interface PostListItem {
  id: number;
  title: string;
  slug: string;
  state: string;
  published_at: string | null;
}

export interface TagListItem {
  id: number;
  name: string;
  slug: string;
  category: string | null;
}
