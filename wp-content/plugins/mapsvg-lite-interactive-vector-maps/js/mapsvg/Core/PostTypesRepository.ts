// MapSVG PostTypesRepository for frontend

import { Schema } from "@/Infrastructure/Server/Schema"
import { Repository } from "./Repository"
import { MapSVGMap } from "@/Map/Map"

export interface PostTypeTaxonomy {
  name: string
  label: string
  items: Array<{ id: number; name: string; slug: string }>
}

export interface PostTypeMeta {
  name: string
  type: string
  label: string
}

export interface PostTypeDetails {
  postType: string
  taxonomy: PostTypeTaxonomy[]
  meta: PostTypeMeta[]
}

export class PostTypesRepository extends Repository {
  baseUrl: string

  /**
   * Fetch field values for a post type
   */
  async getOptionsByField(fieldName: string): Promise<any> {
    if (fieldName === "post_status") {
      return new Promise((resolve) => {
        resolve([
          { label: "Published", value: "publish" },
          { label: "Draft", value: "draft" },
          { label: "Pending", value: "pending" },
          { label: "Private", value: "private" },
          { label: "Trash", value: "trash" },
          { label: "Future", value: "future" },
          { label: "Scheduled", value: "scheduled" },
          { label: "Pending Review", value: "pending-review" },
          { label: "Pending Import", value: "pending-import" },
          { label: "Auto Draft", value: "auto-draft" },
          { label: "Inherit", value: "inherit" },
        ])
      })
    } else if (fieldName.indexOf("meta.") === 0) {
      fieldName = fieldName.replace("meta.", "")
      const response = await this.getMetaValues(fieldName)
      const options = response.items
        .filter((value) => value !== null && typeof value !== "undefined")
        .map((value) => ({ label: value, value: value }))
      return new Promise((resolve) => {
        resolve(options)
      })
    } else if (fieldName.indexOf("taxonomy.") === 0) {
      fieldName = fieldName.replace("taxonomy.", "")
      const response = await this.getTaxonomyValues(fieldName)
      const options = response.items
        .filter((value) => value !== null && typeof value !== "undefined")
        .map((value) => ({ label: value, value: value }))
      return new Promise((resolve) => {
        resolve(options)
      })
    } else {
      const request = this.getRequest("getFieldValues", {
        fieldName,
        name: this.schema.postType,
      })
      return new Promise((resolve, reject) => {
        this.server
          .get(request.url)
          .done((response) => {
            const data = response.items
              .filter((item) => item !== null && typeof item !== "undefined")
              .map((item) => {
                if (typeof item === "object") {
                  return {
                    label: item.label,
                    value: item.value,
                  }
                } else {
                  return {
                    label: item,
                    value: item,
                  }
                }
              })
            resolve(data)
          })
          .fail(reject)
      })
    }
  }

  /**
   * Fetch taxonomy values for a post type
   */
  getTaxonomyValues(fieldName: string): Promise<any> {
    const request = this.getRequest("getTaxonomyValues", { fieldName, name: this.schema.postType })
    return new Promise((resolve, reject) => {
      this.server.get(request.url).done(resolve).fail(reject)
    })
  }

  /**
   * Fetch meta values for a post type
   */
  getMetaValues(fieldName: string): Promise<any> {
    const request = this.getRequest("getMetaValues", { fieldName, name: this.schema.postType })
    return new Promise((resolve, reject) => {
      this.server.get(request.url).done(resolve).fail(reject)
    })
  }

  /**
   * Fetch a post type by its name
   */
  getPostFields(): Promise<any> {
    const request = this.getRequest("get", {})
    return new Promise((resolve, reject) => {
      this.server.get(request.url).done(resolve).fail(reject)
    })
  }

  // ... existing code ...
}
