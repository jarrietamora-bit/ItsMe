package com.itsme.app.ui.articles
import android.content.Intent
import android.os.Bundle
import android.view.*
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.databinding.FragmentArticlesBinding
import com.itsme.app.utils.SessionManager
import com.itsme.app.viewmodel.ArticleViewModel

class ArticlesFragment : Fragment() {
    private var _b: FragmentArticlesBinding? = null
    private val b get() = _b!!
    private val vm: ArticleViewModel by viewModels()

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?) =
        FragmentArticlesBinding.inflate(i, c, false).also { _b = it }.root

    override fun onViewCreated(v: View, s: Bundle?) {
        val tid = SessionManager.getTravelId(requireContext())
        vm.setTravel(tid)
        val adapter = ArticleAdapter(
            onEdit = { a -> startActivity(Intent(requireContext(), ArticleFormActivity::class.java).apply {
                putExtra("id",a.id); putExtra("name",a.name); putExtra("store",a.store)
                putExtra("cost",a.costPrice); putExtra("sale",a.salePrice)
                putExtra("photo",a.photoPath); putExtra("tid",a.travelDateId) }) },
            onDelete = { a -> AlertDialog.Builder(requireContext()).setTitle("Eliminar")
                .setMessage("¿Eliminar ${a.name}?").setPositiveButton("Eliminar") { _,_ -> vm.delete(a) }
                .setNegativeButton("Cancelar", null).show() }
        )
        b.rvArticles.layoutManager = LinearLayoutManager(requireContext())
        b.rvArticles.adapter = adapter
        vm.articles.observe(viewLifecycleOwner) { adapter.submitList(it) }
        b.fabAddArticle.setOnClickListener {
            startActivity(Intent(requireContext(), ArticleFormActivity::class.java).apply { putExtra("tid", tid) })
        }
    }
    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
