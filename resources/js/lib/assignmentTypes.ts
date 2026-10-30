export type AssignmentChoice = {
    id?: string;
    choice_text: string;
    is_correct: boolean;
};

export type AssignmentQuestion = {
    id?: string;
    question: string;
    points: number;
    choices: AssignmentChoice[];
};

export type AssignmentDraft = {
    title: string;
    type: 'essay' | 'mcq' | 'code';
    category: 'quiz' | 'exam' | 'activity' | 'project' | null;
    language: string | null;
    instructions: string;
    rubric: string;
    questions: AssignmentQuestion[];
};
