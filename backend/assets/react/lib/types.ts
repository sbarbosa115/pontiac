// The API's types, generated from its OpenAPI schema (`npm run api:types` → assets/types/api.d.ts): the Output
// DTOs in src/Api/Output, by class name. Pages name what an endpoint returns with them, so a field the backend
// renames or removes is a type error here rather than a blank cell in production.
import type { components, paths } from '../../types/api';

export type Schemas = components['schemas'];

/** `Schema<'TeamMemberOutput'>` is what src/Api/Output/TeamMemberOutput.php serializes to. */
export type Schema<Name extends keyof Schemas> = Schemas[Name];

/** An amount as the API sends it: a decimal string and its currency, never a float. */
export interface Money {
    amount: string;
    currency: string;
}

type JsonOf<Operation> = Operation extends { responses: infer Responses }
    ? Responses extends { 200: { content: { 'application/json': infer Body } } }
        ? Body
        : Responses extends { 201: { content: { 'application/json': infer Body } } }
          ? Body
          : never
    : never;

/**
 * What `GET path` answers, by the route as the API declares it: `Get<'/api/admin/team'>`. For the endpoints
 * whose body is not one Output DTO: a page (`{ items, total, page, perPage }`), a list (`{ items }`) or a keyed one.
 */
export type Get<Path extends keyof paths> = JsonOf<paths[Path]['get']>;

/** What `POST path` answers (200 or 201). */
export type Post<Path extends keyof paths> = JsonOf<paths[Path]['post']>;
