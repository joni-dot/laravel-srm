export type Comment = {
    id: number;
    body: string;
    author: string;
    created_at: string;
    edited_at: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type CommentFeed = {
    /** Oldest first, so the newest comment sits next to the box for writing a new one. */
    items: Comment[];
    /** How many comments to ask for to see older ones, or null when there are none. */
    next_limit: number | null;
};
