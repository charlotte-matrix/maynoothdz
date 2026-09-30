import { mapsvgCore } from "@/Core/Mapsvg"
import { Events } from "../../Core/Events"
import { SchemaField } from "../../Infrastructure/Server/SchemaField"
import { FormBuilder } from "../FormBuilder"
import { FormElement, FormElementOptions } from "./FormElement"
import { FormElementInterface } from "./FormElementInterface"
import { Server } from "@/Infrastructure/Server/Server"

const $ = jQuery

export interface FormElementWithOptionsInterface extends FormElementInterface {
  options: FormElementOptions[]
  optionsDict: { [key: string]: any }
  asyncOptions: boolean
  optionsSource: {
    type: "region" | "object" | "post"
    name: string
    fieldCategory: "meta" | "taxonomy" | "custom"
    fieldName: string
  } | null
}
/**
 *
 */
export class FormElementWithOptions extends FormElement implements FormElementWithOptionsInterface {
  options: FormElementOptions[]
  optionsDict: { [key: string]: any }
  asyncOptions: boolean
  optionsSource: {
    type: "region" | "object" | "post"
    name: string
    fieldCategory: "meta" | "taxonomy" | "custom"
    fieldName: string
  } | null

  constructor(options: SchemaField, formBuilder: FormBuilder, external: { [key: string]: any }) {
    super(options, formBuilder, external)
    this.options = options.options ?? []
    this.optionsDict = options.optionsDict ?? {}
    this.asyncOptions = options.asyncOptions ?? false
    this.optionsSource = options.optionsSource ?? null
  }

  async getOptions() {
    let url = ""

    if (this.asyncOptions && this.optionsSource) {
      const { type, name, fieldCategory, fieldName } = this.optionsSource
      if (type === "region") {
        // /regions/{name}/distinct/{fieldName}
        url = `/regions/${name}/distinct/${fieldName}`
      } else if (type === "object") {
        // /objects/{name}/distinct/{fieldName}
        url = `/objects/${name}/distinct/${fieldName}`
      } else if (type === "post") {
        if (fieldCategory === "meta") {
          // /post-types/{name}/meta/{fieldName}
          url = `/post-types/${name}/meta/${fieldName}`
        } else if (fieldCategory === "taxonomy") {
          // /post-types/{name}/taxonomy/{fieldName}
          url = `/post-types/${name}/taxonomy/${fieldName}`
        } else if (fieldCategory === "custom") {
          // fallback for custom fields if needed
          url = `/post-types/${name}/field/${fieldName}`
        }
      }

      // Fetch and return options
      const response = await fetch(url)
      const responseData = await response.json()
      return responseData.items.map((value: any) => ({
        label: value,
        value: value,
      }))
    } else {
      return this.options
    }
  }
}
