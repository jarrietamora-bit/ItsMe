package com.itsme.app.ui.articles
import android.view.*
import androidx.recyclerview.widget.*
import com.bumptech.glide.Glide
import com.itsme.app.R
import com.itsme.app.data.entities.Article
import com.itsme.app.databinding.ItemArticleBinding
import java.io.File

class ArticleAdapter(val onEdit: (Article)->Unit, val onDelete: (Article)->Unit) :
    ListAdapter<Article, ArticleAdapter.VH>(object: DiffUtil.ItemCallback<Article>() {
        override fun areItemsTheSame(a: Article, b: Article) = a.id==b.id
        override fun areContentsTheSame(a: Article, b: Article) = a==b
    }) {
    inner class VH(val b: ItemArticleBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemArticleBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val a = getItem(pos)
        h.b.tvArticleName.text = a.name
        h.b.tvStore.text = a.store
        h.b.tvCost.text = "Costo: \$${String.format("%.2f", a.costPrice)}"
        h.b.tvSale.text = "Venta: \$${String.format("%.2f", a.salePrice)}"
        h.b.tvProfit.text = "Ganancia: \$${String.format("%.2f", a.salePrice - a.costPrice)}"
        if (!a.photoPath.isNullOrEmpty())
            Glide.with(h.b.root.context).load(File(a.photoPath)).placeholder(R.drawable.ic_image).into(h.b.ivArticle)
        else h.b.ivArticle.setImageResource(R.drawable.ic_image)
        h.b.btnEdit.setOnClickListener { onEdit(a) }
        h.b.btnDelete.setOnClickListener { onDelete(a) }
    }
}
