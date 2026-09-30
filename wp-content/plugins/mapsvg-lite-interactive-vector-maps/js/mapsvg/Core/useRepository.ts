import { Schema, SchemaOptions } from "@/Infrastructure/Server/Schema"
import { SchemaModelSchema } from "@/Infrastructure/Server/SchemaModel"
import { SchemaRepository } from "@/Infrastructure/Server/SchemaRepository"
import { MapSVGMap } from "@/Map/Map"
import { MapsRepository } from "@/Map/MapsRepository"
import { ArrayIndexed } from "./ArrayIndexed"
import { MiddlewareType } from "./Middleware"
import { Repository } from "./Repository"
import { PostTypesRepository } from "./PostTypesRepository"

function isSchemaOptions(obj: any): obj is SchemaOptions {
  return (
    typeof obj === "object" && obj !== null && typeof obj.name === "string"
    // Optionally check for other required properties
  )
}

function useRepository(
  name: "regions" | ["regions", string] | SchemaOptions,
  map: MapSVGMap,
): Repository
function useRepository(
  name: "objects" | ["objects", string] | SchemaOptions,
  map: MapSVGMap,
): Repository
function useRepository(name: "schemas" | SchemaOptions, map: MapSVGMap): SchemaRepository
function useRepository(name: "maps" | SchemaOptions, map: MapSVGMap): MapsRepository
function useRepository(
  name: ["postTypes", string] | SchemaOptions,
  map: MapSVGMap,
): PostTypesRepository
function useRepository(
  _name: string | ["postTypes", string] | ["regions", string] | ["objects", string] | SchemaOptions,
  map: MapSVGMap,
): Repository | SchemaRepository | MapsRepository | PostTypesRepository {
  let name: string, type: string
  let schema: Schema
  let repo: Repository | SchemaRepository | MapsRepository | PostTypesRepository

  const repos = {
    maps: {
      class: MapsRepository,
      schema: {
        name: "maps",
        objectNameSingular: "map",
        objectNamePlural: "maps",
        apiEndpoints: [
          { url: "maps", method: "GET", name: "index" },
          { url: "maps/[:id]", method: "GET", name: "show" },
          { url: "maps", method: "POST", name: "create" },
          { url: "maps/createFromV2", method: "POST", name: "createFromV2" },
          { url: "maps/[:id]/copy", method: "POST", name: "copy" },
          { url: "maps/[:id]", method: "PUT", name: "update" },
          { url: "maps/[:id]", method: "DELETE", name: "delete" },
          { url: "maps", method: "DELETE", name: "clear" },
        ],
        fields: [
          {
            name: "id",
            label: "ID",
            type: "ID",
            db_type: "int(11)",
            visible: true,
            protected: true,
          },
          {
            name: "title",
            label: "Title",
            type: "text",
            db_type: "varchar(255)",
            visible: true,
          },
          {
            name: "options",
            label: "Options",
            type: "textarea",
            db_type: "longtext",
            visible: true,
          },
          {
            name: "svgFilePath",
            label: "SVG file path",
            type: "text",
            db_type: "varchar(512)",
            visible: true,
          },
          {
            name: "svgFileLastChanged",
            label: "SVG file last changed",
            type: "text",
            db_type: "int(11)",
            visible: true,
          },
          {
            name: "version",
            label: "Version",
            type: "text",
            db_type: "varchar(50)",
            visible: true,
          },
          {
            name: "status",
            label: "Status",
            type: "text",
            db_type: "tinyint",
            visible: false,
          },
          {
            name: "statusChangedAt",
            label: "Status Changed At",
            type: "text",
            db_type: "timestamp",
            visible: false,
          },
        ],
      },
    },
    regions: {
      class: Repository,
      schema: {
        objectNameSingular: "region",
        objectNamePlural: "regions",
        apiEndpoints: [
          { url: "regions/[:name]", method: "GET", name: "index" },
          { url: "regions/[:name]/[:id]", method: "GET", name: "show" },
          { url: "regions/[:name]/[:id]/distinct/[:fieldName]", method: "GET", name: "distinct" },
          { url: "regions/[:name]", method: "POST", name: "create" },
          { url: "regions/[:name]/[:id]", method: "PUT", name: "update" },
          { url: "regions/[:name]/[:id]/import", method: "POST", name: "import" },
          { url: "regions/[:name]/[:id]", method: "DELETE", name: "delete" },
        ],
      },
    },
    objects: {
      class: Repository,
      schema: {
        objectNameSingular: "object",
        objectNamePlural: "objects",
        apiEndpoints: [
          { url: "objects/[:name]", method: "GET", name: "index" },
          { url: "objects/[:name]/[:id]", method: "GET", name: "show" },
          { url: "objects/[:name]/[:id]/distinct/[:fieldName]", method: "GET", name: "distinct" },
          { url: "objects/[:name]", method: "POST", name: "create" },
          { url: "objects/[:name]/[:id]", method: "PUT", name: "update" },
          { url: "objects/[:name]/[:id]", method: "DELETE", name: "delete" },
          { url: "objects/[:name]/[:id]/import", method: "POST", name: "import" },
          { url: "objects/[:name]", method: "DELETE", name: "clear" },
        ],
      },
    },
    schemas: {
      class: SchemaRepository,
      schema: SchemaModelSchema,
    },
    logs: {
      class: Repository,
      schema: {
        objectNameSingular: "log",
        objectNamePlural: "logs",
      },
    },
    tokens: {
      class: Repository,
      schema: {
        name: "tokens",
        objectNameSingular: "token",
        objectNamePlural: "tokens",
        apiEndpoints: new ArrayIndexed("name", [
          {
            name: "index",
            url: "tokens",
            method: "GET",
          },
          {
            name: "create",
            url: "tokens",
            method: "POST",
          },
          {
            name: "delete",
            url: "tokens/[:id]",
            method: "DELETE",
          },
        ]),
        fields: [
          {
            name: "id",
            label: "ID",
            type: "id",
            db_type: "int(11)",
            protected: true,
            auto_increment: true,
          },
          {
            label: "Token",
            name: "token",
            type: "text",
            db_type: "varchar(255)",
          },
          {
            label: "Token first four",
            name: "tokenFirstFour",
            type: "text",
            db_type: "varchar(255)",
          },
          {
            label: "Hashed token",
            name: "hashedToken",
            type: "text",
            db_type: "varchar(255)",
          },
          {
            name: "createdAt",
            label: "Created at",
            type: "datetime",
            db_type: "datetime",
          },
          {
            name: "lastUsedAt",
            label: "Last used at",
            type: "datetime",
            db_type: "datetime",
          },
          {
            name: "accessRights",
            label: "Access rights",
            type: "json",
            db_type: "json",
          },
        ],
      },
    },
    postTypes: {
      class: PostTypesRepository,
      schema: {
        objectNameSingular: "postType",
        objectNamePlural: "postTypes",
        apiEndpoints: [
          { url: "post-types", method: "GET", name: "index" },
          { url: "post-types/[:name]", method: "GET", name: "get" },
          {
            url: "post-types/[:name]/taxonomy/[:fieldName]",
            method: "GET",
            name: "getTaxonomyValues",
          },
          {
            url: "post-types/[:name]/meta/[:fieldName]",
            method: "GET",
            name: "getMetaValues",
          },
          {
            url: "post-types/[:name]/field/[:fieldName]",
            method: "GET",
            name: "getFieldValues",
          },
        ],
      },
    },
  }

  if (isSchemaOptions(_name)) {
    schema = new Schema(_name)
    let repoType = ""
    if (schema.type) {
      if (schema.type === "post" || schema.type === "api") {
        repoType = "objects"
      } else {
        repoType = schema.type + "s"
      }
    } else if (schema.objectNamePlural) {
      repoType = schema.objectNamePlural
    } else {
      throw new Error("Invalid schema options")
    }

    repo = new repos[repoType].class(schema, map)
  } else {
    if (typeof _name === "string") {
      name = _name
      type = _name
    } else if (Array.isArray(_name) && _name.length === 2) {
      type = _name[0]
      name = _name[1]
    }

    const schemaOptions = { ...repos[type].schema }
    if (["postTypes", "regions", "objects"].includes(type)) {
      schemaOptions.apiEndpoints.forEach((endpoint) => {
        endpoint.url = endpoint.url.replace(/\[:name\]/g, `${name}`)
      })
    }
    if (type === "postTypes") {
      schemaOptions.postType = _name[1]
    }
    schema = new Schema(schemaOptions)
    repo = new repos[type].class(schema, map)
  }

  if ((type === "regions" || type === "objects" || type === "posts") && map) {
    const middlewares = map.middlewares.middlewares.filter(
      (m) => m.name === MiddlewareType.REQUEST || m.name === MiddlewareType.RESPONSE,
    )
    middlewares.forEach((middleware) => {
      repo.middlewares.add(middleware.name, middleware.handler, middleware.options.unique)
    })
  }

  repo.init()
  return repo
}

export { useRepository }
