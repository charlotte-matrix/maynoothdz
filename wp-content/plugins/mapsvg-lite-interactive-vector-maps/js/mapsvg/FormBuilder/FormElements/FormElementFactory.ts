import { deferredToPromise, ucfirst } from "@/Core/Utils"
import { ArrayIndexed } from "../../Core/ArrayIndexed"
import { SchemaField, SchemaFieldProps } from "../../Infrastructure/Server/SchemaField"
import { MapSVGMap } from "../../Map/Map"
import { FormBuilder } from "../FormBuilder"
import { FormElement } from "./FormElement"
import * as FormElementTypes from "./index.js"
import { PostTypesRepository } from "@/Core/PostTypesRepository"
import { useRepository } from "@/Core/useRepository"
import { Schema } from "@/Infrastructure/Server/Schema"
const $ = jQuery

export type BaseFormElementExtraParams = {
  mediaUploader: any
  filtersMode: boolean
  showNames: boolean
  mapIsGeo?: boolean
}

export type EditingFormElementExtraParams = BaseFormElementExtraParams & {
  databaseFields?: string[]
  databaseFieldsFilterable?: string[]
  databaseFieldsFilterableShort?: string[]
  regionFields?: string[]
  regionFieldsFilterable?: string[]
  regions?: ArrayIndexed<any>
  postTypeData?: any | null
}

/**
 *
 */
export class FormElementFactory {
  mapsvg: MapSVGMap
  formBuilder: FormBuilder
  namespace: string
  editMode: boolean
  filtersMode: boolean
  mediaUploader: any
  showNames: boolean
  types: Record<string, typeof FormElement>
  extraParams: EditingFormElementExtraParams | null

  constructor(options: {
    mapsvg: MapSVGMap
    formBuilder: FormBuilder
    editMode: boolean
    filtersMode: boolean
    namespace: string
    mediaUploader: any
    showNames?: boolean
  }) {
    this.mapsvg = options.mapsvg
    this.editMode = options.editMode
    this.filtersMode = options.filtersMode
    this.namespace = options.namespace
    this.mediaUploader = options.mediaUploader
    this.formBuilder = options.formBuilder
    this.showNames = options.showNames !== false
    this.extraParams = null
  }

  async init(): Promise<void> {
    this.extraParams = await this.getExtraParams()
  }

  create(schemaOptions: SchemaFieldProps | SchemaField): FormElementTypes.FormElementInterface {
    this.types = {
      checkbox: FormElementTypes.CheckboxFormElement,
      checkboxes: FormElementTypes.CheckboxesFormElement,
      date: FormElementTypes.DateFormElement,
      // START distance_search
      distance: FormElementTypes.DistanceFormElement,
      // REPLACE
      // distance: FormElementTypes.EmptyFormElement,
      // END
      empty: FormElementTypes.EmptyFormElement,
      id: FormElementTypes.IdFormElement,
      image: FormElementTypes.ImagesFormElement,
      location: FormElementTypes.LocationFormElement,
      modal: FormElementTypes.ModalFormElement,
      post: FormElementTypes.PostFormElement,
      radio: FormElementTypes.RadioFormElement,
      region: FormElementTypes.RegionsFormElement,
      save: FormElementTypes.SaveFormElement,
      
      select: FormElementTypes.SelectFormElement,
      status: FormElementTypes.StatusFormElement,
      text: FormElementTypes.TextFormElement,
      textarea: FormElementTypes.TextareaFormElement,
      title: FormElementTypes.TitleFormElement,
      colorpicker: FormElementTypes.ColorPickerFormElement,
    }

    if (!this.extraParams) {
      throw new Error("FormElementFactory.init() must be called before create().")
    }

    const formElement = new this.types[schemaOptions.type](
      schemaOptions instanceof SchemaField ? schemaOptions : new SchemaField(schemaOptions),
      this.formBuilder,
      this.extraParams,
    )
    formElement.init()
    return formElement
  }

  async getExtraParams(): Promise<EditingFormElementExtraParams> {
    const databaseFields: string[] = []
    let databaseFieldsFilterable: string[] = []
    const databaseFieldsFilterableShort: string[] = []
    const regionFields: string[] = []
    let regionFieldsFilterable: string[] = []
    const regions = new ArrayIndexed("id")
    const mapIsGeo = false

    const extraParams: EditingFormElementExtraParams = {
      mediaUploader: this.mediaUploader,
      filtersMode: this.filtersMode,
      showNames: this.showNames,
      mapIsGeo: this.mapsvg ? this.mapsvg.isGeo() : false,
    }

    // If we are not in edit mode, return the extra params immediately
    if (!this.editMode) {
      return extraParams
    }

    const getFilterableFields = async (schema: Schema) => {
      const databaseFields: string[] = []
      const type = schema.type === "post" || schema.type === "api" ? "Object" : ucfirst(schema.type)

      for (const obj of schema.getFields()) {
        if (obj.type == "location" || obj.type == "image") {
          continue
        }

        // START filters_posts
        if (obj.type == "post") {
          const postTypesRepo = useRepository(["postTypes", obj.post_type], this.mapsvg)
          databaseFields.push(`${type}.post.post_title`)
          databaseFields.push(`${type}.post.post_status`)
          const response = await postTypesRepo.getPostFields()

          const { postType, meta, taxonomy } = response

          if (meta) {
            meta
              .filter((meta) => !["mapsvg_location", "footnotes"].includes(meta.name))
              .forEach((meta) => {
                databaseFields.push(`${type}.post.meta.${meta.name}`)
              })
          }
          if (taxonomy) {
            taxonomy.forEach((taxonomy) => {
              databaseFields.push(`${type}.post.taxonomy.${taxonomy.name}`)
            })
          }
        } else {
          databaseFields.push(`${type}.${obj.name}`)
        }
        // REPLACE
        // databaseFields.push(`${type}.${obj.name}`)
        // END
      }
      return databaseFields
    }

    if (this.mapsvg) {
      databaseFieldsFilterable = await getFilterableFields(
        this.mapsvg.objectsRepository.getSchema(),
      )
      regionFieldsFilterable = await getFilterableFields(this.mapsvg.regionsRepository.getSchema())
    }

    return {
      ...extraParams,
      databaseFields: databaseFields,
      databaseFieldsFilterable: databaseFieldsFilterable,
      databaseFieldsFilterableShort: databaseFieldsFilterableShort,
      regionFields: regionFields,
      regionFieldsFilterable: regionFieldsFilterable,
      regions: regions,
      postTypeData: null,
    }
  }
}
